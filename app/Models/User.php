<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Subscription;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Location;
use App\Models\LocationHistory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'referal_code',
        'photo',
    ];

    public static $rules = [
        'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:3072',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->referal_code)) {
                do {
                    $code = strtoupper(Str::random(5));
                } while (static::where('referal_code', $code)->exists());
                
                $user->referal_code = $code;
            }
        });

        static::created(function (User $user) {
            $circle = $user->circle()->create([
                'name' => null,
                'referal_code' => $user->referal_code,
            ]);

            $circle->members()->create([
                'user_id' => $user->id,
                'role' => 'ketua guild',
                'status' => 'active',
            ]);
        });
    }

    public function circle(): HasOne
    {
        return $this->hasOne(Circle::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CircleMember::class);
    }

    public function location(): HasOne
    {
        return $this->hasOne(Location::class);
    }

    public function locationHistories(): HasMany
    {
        return $this->hasMany(LocationHistory::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where('expired_at', '>', now())
            ->latestOfMany();
    }

    public function activePremiumSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('plan_name', Subscription::PLAN_PREMIUM)
            ->where('status', 'active')
            ->where('expired_at', '>', now())
            ->latestOfMany();
    }

    public function isPremium(): bool
    {
        return $this->activePremiumSubscription()->exists();
    }
}
