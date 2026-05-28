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
        return $this->createSnapTransaction($payment)['token'];
    }

    public function createSnapTransaction(SubscriptionPayment $payment): array
    {
        $serverKey = config('services.midtrans.server_key');

        if (! $serverKey) {
            throw new RuntimeException('Midtrans server key is not configured.');
        }

        $payload = [
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
        ];

        if (config('services.midtrans.finish_redirect_url')) {
            $payload['callbacks'] = [
                'finish' => config('services.midtrans.finish_redirect_url'),
            ];
        }

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->post(config('services.midtrans.snap_url'), $payload)
            ->throw();

        return [
            'token' => $response->json('token'),
            'redirect_url' => $response->json('redirect_url'),
        ];
    }

    public function snapRedirectUrl(?string $snapToken): ?string
    {
        if (! $snapToken) {
            return null;
        }

        return rtrim(config('services.midtrans.snap_redirect_base_url'), '/').'/'.$snapToken;
    }
}
