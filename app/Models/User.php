<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'phone', 'referal_code'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
}
