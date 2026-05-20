<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    public const PLAN_FREE = 'free';
    public const PLAN_PREMIUM = 'premium';

    private const ALLOWED_PLAN_NAMES = [
        self::PLAN_FREE,
        self::PLAN_PREMIUM,
    ];

    protected $fillable = [
        'user_id',
        'plan_name',
        'status',
        'started_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Subscription $subscription) {
            $planName = $subscription->plan_name ?: self::PLAN_FREE;

            if (! in_array($planName, self::ALLOWED_PLAN_NAMES, true)) {
                throw ValidationException::withMessages([
                    'plan_name' => 'The selected plan name is invalid.',
                ]);
            }

            $subscription->plan_name = $planName;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
