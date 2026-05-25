<?php

namespace App\Services;

use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransService
{
    public function isValidNotificationSignature(
        string $orderId,
        string $statusCode,
        string $grossAmount,
        string $signatureKey
    ): bool {
        $serverKey = config('services.midtrans.server_key');

        if (! $serverKey) {
            return false;
        }

        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expectedSignature, $signatureKey);
    }

    public function createSnapToken(SubscriptionPayment $payment): string
    {
        $serverKey = config('services.midtrans.server_key');

        if (! $serverKey) {
            throw new RuntimeException('Midtrans server key is not configured.');
        }

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->post(config('services.midtrans.snap_url'), [
                'transaction_details' => [
                    'order_id' => $payment->order_id,
                    'gross_amount' => (int) $payment->amount,
                ],
                'customer_details' => [
                    'first_name' => $payment->user?->name,
                    'email' => $payment->user?->email,
                    'phone' => $payment->user?->phone,
                ],
                'item_details' => [
                    [
                        'id' => 'premium-subscription',
                        'price' => (int) $payment->amount,
                        'quantity' => 1,
                        'name' => 'Premium Subscription',
                    ],
                ],
                'expiry' => [
                    'unit' => 'minutes',
                    'duration' => (int) config('services.subscription.payment_expiry_minutes', 60),
                ],
            ])
            ->throw();

        return $response->json('token');
    }
}
