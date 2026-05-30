<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->expireExpiredPendingPayments($user->id);

        $activeSubscription = $user->activePremiumSubscription()->first();
        $pendingPayment = $this->latestPendingPremiumPayment($user->id);

        return response()->json([
            'message' => 'Subscription retrieved successfully',
            'data' => [
                'is_premium' => $activeSubscription !== null,
                'plan_name' => $activeSubscription?->plan_name ?? Subscription::PLAN_FREE,
                'subscription' => $activeSubscription,
                'active_subscription' => $activeSubscription,
                'pending_payment' => $pendingPayment,
            ],
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->expireExpiredPendingPayments($user->id);

        $payments = SubscriptionPayment::with('subscription')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Subscription payments retrieved successfully',
            'data' => [
                'payments' => $payments,
            ],
        ]);
    }

    public function upgradeToPremium(Request $request, MidtransService $midtrans): JsonResponse
    {
        $user = $request->user();

        $this->expireExpiredPendingPayments($user->id);

        $activePremiumSubscription = $user->activePremiumSubscription()->first();

        if ($activePremiumSubscription) {
            return response()->json([
                'message' => 'User already has an active premium subscription',
                'data' => [
                    'is_premium' => true,
                    'plan_name' => Subscription::PLAN_PREMIUM,
                    'subscription' => $activePremiumSubscription,
                ],
            ]);
        }

        $pendingPayment = $this->latestPendingPremiumPayment($user->id);

        if ($pendingPayment) {
            return response()->json([
                'message' => 'User already has a pending premium subscription payment',
                'data' => [
                    'is_premium' => false,
                    'plan_name' => Subscription::PLAN_FREE,
                    'subscription' => $pendingPayment->subscription,
                    'payment' => $pendingPayment,
                    'snap_token' => $pendingPayment->snap_token,
                    'redirect_url' => $midtrans->snapRedirectUrl($pendingPayment->snap_token),
                ],
            ]);
        }

        $amount = (int) config('services.subscription.premium_price', 25000);
        $paymentExpiredAt = now()->addMinutes((int) config('services.subscription.payment_expiry_minutes', 60));

        [$subscription, $payment] = DB::transaction(function () use ($user, $amount, $paymentExpiredAt) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_name' => Subscription::PLAN_PREMIUM,
                'status' => Subscription::STATUS_PENDING,
                'started_at' => null,
                'expired_at' => null,
            ]);

            $payment = SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'order_id' => $this->generateSubscriptionOrderId($user->id),
                'amount' => $amount,
                'payment_status' => SubscriptionPayment::STATUS_PENDING,
                'expired_at' => $paymentExpiredAt,
            ]);

            return [$subscription, $payment];
        });

        try {
            $snapTransaction = $midtrans->createSnapTransaction($payment->load('user'));
        } catch (Throwable $exception) {
            Log::error('Failed to create Midtrans subscription payment.', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'error' => $exception->getMessage(),
            ]);

            DB::transaction(function () use ($subscription, $payment) {
                $payment->update([
                    'payment_status' => SubscriptionPayment::STATUS_FAILED,
                ]);

                $subscription->update([
                    'status' => Subscription::STATUS_CANCELLED,
                ]);
            });

            return response()->json([
                'message' => 'Failed to create Midtrans payment',
                'data' => [
                    'is_premium' => false,
                    'plan_name' => Subscription::PLAN_FREE,
                    'subscription' => $subscription->fresh(),
                    'payment' => $payment->fresh(),
                ],
            ], 502);
        }

        $payment->update([
            'snap_token' => $snapTransaction['token'],
        ]);

        return response()->json([
            'message' => 'Subscription payment created successfully',
            'data' => [
                'is_premium' => false,
                'plan_name' => Subscription::PLAN_FREE,
                'subscription' => $subscription,
                'payment' => $payment->fresh(),
                'snap_token' => $snapTransaction['token'],
                'redirect_url' => $snapTransaction['redirect_url'],
            ],
        ], 201);
    }

    private function generateSubscriptionOrderId(int $userId): string
    {
        return 'SUB-'.now()->format('YmdHis').'-'.$userId.'-'.Str::upper(Str::random(6));
    }

    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activePremiumSubscription()->first();

        if (! $subscription) {
            return response()->json([
                'message' => 'Active premium subscription not found',
                'data' => [
                    'is_premium' => false,
                    'plan_name' => Subscription::PLAN_FREE,
                    'subscription' => null,
                ],
            ], 404);
        }

        DB::transaction(function () use ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
            ]);
        });

        return response()->json([
            'message' => 'Subscription cancelled successfully',
            'data' => [
                'is_premium' => false,
                'plan_name' => Subscription::PLAN_FREE,
                'subscription' => $subscription->fresh(),
            ],
        ]);
    }

    public function cancelPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        $this->expireExpiredPendingPayments($request->user()->id);

        $payment = SubscriptionPayment::with('subscription')
            ->where('user_id', $request->user()->id)
            ->where('order_id', $validated['order_id'])
            ->first();

        if (! $payment) {
            return response()->json([
                'message' => 'Subscription payment not found',
                'data' => null,
            ], 404);
        }

        if ($payment->payment_status !== SubscriptionPayment::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only pending subscription payment can be cancelled',
                'data' => [
                    'payment' => $payment,
                ],
            ], 409);
        }

        DB::transaction(function () use ($payment) {
            $payment->update([
                'payment_status' => SubscriptionPayment::STATUS_CANCELLED,
            ]);

            if ($payment->subscription?->status === Subscription::STATUS_PENDING) {
                $payment->subscription->update([
                    'status' => Subscription::STATUS_CANCELLED,
                ]);
            }
        });

        return response()->json([
            'message' => 'Subscription payment cancelled successfully',
            'data' => [
                'is_premium' => false,
                'plan_name' => Subscription::PLAN_FREE,
                'payment' => $payment->fresh(),
            ],
        ]);
    }

    private function latestPendingPremiumPayment(int $userId): ?SubscriptionPayment
    {
        return SubscriptionPayment::with('subscription')
            ->where('user_id', $userId)
            ->where('payment_status', SubscriptionPayment::STATUS_PENDING)
            ->where('expired_at', '>', now())
            ->whereHas('subscription', function ($query) {
                $query->where('plan_name', Subscription::PLAN_PREMIUM)
                    ->where('status', Subscription::STATUS_PENDING);
            })
            ->latest()
            ->first();
    }

    private function expireExpiredPendingPayments(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $payments = SubscriptionPayment::with('subscription')
                ->where('user_id', $userId)
                ->where('payment_status', SubscriptionPayment::STATUS_PENDING)
                ->whereNotNull('expired_at')
                ->where('expired_at', '<=', now())
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                $payment->update([
                    'payment_status' => SubscriptionPayment::STATUS_EXPIRED,
                ]);

                if ($payment->subscription?->status === Subscription::STATUS_PENDING) {
                    $payment->subscription->update([
                        'status' => Subscription::STATUS_EXPIRED,
                    ]);
                }
            }
        });
    }
}
