<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransNotificationController extends Controller
{
    public function handle(Request $request, MidtransService $midtrans): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string'],
            'status_code' => ['required', 'string'],
            'gross_amount' => ['required', 'numeric'],
            'signature_key' => ['required', 'string'],
            'transaction_status' => ['required', 'string'],
            'transaction_id' => ['nullable', 'string'],
            'payment_type' => ['nullable', 'string'],
            'fraud_status' => ['nullable', 'string'],
        ]);

        if (! $midtrans->isValidNotificationSignature(
            $validated['order_id'],
            $validated['status_code'],
            (string) $request->input('gross_amount'),
            $validated['signature_key']
        )) {
            Log::warning('Invalid Midtrans notification signature.', [
                'order_id' => $validated['order_id'],
                'status_code' => $validated['status_code'],
                'transaction_status' => $validated['transaction_status'],
            ]);

            return response()->json(['message' => 'Invalid Midtrans signature'], 403);
        }

        $payment = DB::transaction(function () use ($validated) {
            $payment = SubscriptionPayment::with('subscription')
                ->where('order_id', $validated['order_id'])
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return null;
            }

            if (number_format((float) $payment->amount, 2, '.', '') !== number_format((float) $validated['gross_amount'], 2, '.', '')) {
                Log::warning('Midtrans notification amount mismatch.', [
                    'order_id' => $validated['order_id'],
                    'expected_amount' => $payment->amount,
                    'received_amount' => $validated['gross_amount'],
                ]);

                return false;
            }

            $status = $validated['transaction_status'];
            $fraudStatus = $validated['fraud_status'] ?? null;

            $payment->update([
                'transaction_id' => $validated['transaction_id'] ?? $payment->transaction_id,
                'payment_method' => $validated['payment_type'] ?? $payment->payment_method,
            ]);

            if ($payment->payment_status === SubscriptionPayment::STATUS_PAID) {
                return $payment->fresh(['subscription']);
            }

            if ($status === 'settlement' || ($status === 'capture' && ($fraudStatus === null || $fraudStatus === 'accept'))) {
                $payment->update([
                    'payment_status' => SubscriptionPayment::STATUS_PAID,
                    'paid_at' => $payment->paid_at ?? now(),
                ]);

                if ($payment->subscription?->status !== Subscription::STATUS_ACTIVE) {
                    $payment->subscription?->update([
                        'status' => Subscription::STATUS_ACTIVE,
                        'started_at' => $payment->subscription->started_at ?? now(),
                        'expired_at' => $payment->subscription->expired_at ?? now()->addMonth(),
                    ]);
                }
            } elseif ($status === 'pending') {
                $payment->update(['payment_status' => SubscriptionPayment::STATUS_PENDING]);
            } elseif ($status === 'expire') {
                $payment->update(['payment_status' => SubscriptionPayment::STATUS_EXPIRED]);

                if ($payment->subscription?->status === Subscription::STATUS_PENDING) {
                    $payment->subscription->update(['status' => Subscription::STATUS_EXPIRED]);
                }
            } elseif ($status === 'cancel') {
                $payment->update(['payment_status' => SubscriptionPayment::STATUS_CANCELLED]);

                if ($payment->subscription?->status === Subscription::STATUS_PENDING) {
                    $payment->subscription->update(['status' => Subscription::STATUS_CANCELLED]);
                }
            } elseif (in_array($status, ['deny', 'failure'], true)) {
                $payment->update(['payment_status' => SubscriptionPayment::STATUS_FAILED]);

                if ($payment->subscription?->status === Subscription::STATUS_PENDING) {
                    $payment->subscription->update(['status' => Subscription::STATUS_CANCELLED]);
                }
            }

            return $payment->fresh(['subscription']);
        });

        if ($payment === null) {
            return response()->json(['message' => 'Subscription payment not found'], 404);
        }

        if ($payment === false) {
            return response()->json(['message' => 'Payment amount mismatch'], 422);
        }

        return response()->json([
            'message' => 'Midtrans notification processed successfully',
            'data' => [
                'payment' => $payment,
                'subscription' => $payment->subscription,
            ],
        ]);
    }
}
