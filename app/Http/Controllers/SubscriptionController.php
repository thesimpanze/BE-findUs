<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $subscription = $request->user()->activePremiumSubscription()->first();

        return response()->json([
            'message' => 'Subscription retrieved successfully',
            'data' => [
                'is_premium' => $subscription !== null,
                'plan_name' => $subscription?->plan_name ?? Subscription::PLAN_FREE,
                'subscription' => $subscription,
            ],
        ]);
    }

    public function upgradeToPremium(Request $request): JsonResponse
    {
        $user = $request->user();
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

        $subscription = DB::transaction(function () use ($user) {
            return Subscription::create([
                'user_id' => $user->id,
                'plan_name' => Subscription::PLAN_PREMIUM,
                'status' => 'active',
                'started_at' => now(),
                'expired_at' => now()->addMonth(),
            ]);
        });

        return response()->json([
            'message' => 'Subscription upgraded to premium successfully',
            'data' => [
                'is_premium' => true,
                'plan_name' => Subscription::PLAN_PREMIUM,
                'subscription' => $subscription,
            ],
        ], 201);
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
}
