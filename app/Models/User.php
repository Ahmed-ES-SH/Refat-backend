<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\ResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone', 'date_of_birth', 'gender', 'locale'])]
#[Hidden(['password', 'google_id', 'stripe_customer_id'])]
class User extends Authenticatable implements MustVerifyEmail
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
            'date_of_birth' => 'date',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'locale' => Locale::class,
            'gender' => Gender::class,
        ];
    }

    /**
     * The center this user staffs. Only set for `center_staff` accounts.
     *
     * @return BelongsTo<Center, $this>
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Dependants the patient booked for.
     *
     * @return HasMany<FamilyMember, $this>
     */
    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * Permissions granted by the user's role, returned by `GET /me`.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return $this->role->permissions();
    }

    /**
     * Locale used for outgoing mail and notifications.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale?->value;
    }

    public function isSuspended(): bool
    {
        return ! $this->status->isActive();
    }

    /**
     * Reset links point at the frontend, not a Blade route.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
