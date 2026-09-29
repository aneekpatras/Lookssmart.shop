<?php

namespace App\Models;

use App\Concerns\LogsAuditableActivity;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, LogsAuditableActivity, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * Note: `two_factor_secret`/`two_factor_recovery_codes` are deliberately NOT given an
     * `encrypted` cast here — Fortify's `TwoFactorAuthenticatable` trait already calls
     * `encrypt()`/`decrypt()` on them directly; stacking a cast on top would double-encrypt.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Overrides `CanResetPassword`'s default: `dispatch()->afterResponse()` defers the actual send
     * until after the HTTP response is flushed to the browser, so the Forgot Password request never
     * waits on SMTP — true regardless of `QUEUE_CONNECTION` or whether a worker is running (unlike
     * `ShouldQueue` alone, which only helps once a real async queue + worker exists).
     */
    public function sendPasswordResetNotification($token): void
    {
        dispatch(fn () => $this->notify(new QueuedResetPassword($token)))->afterResponse();
    }

    /** See sendPasswordResetNotification() — same reasoning, for the registration verification email. */
    public function sendEmailVerificationNotification(): void
    {
        dispatch(fn () => $this->notify(new QueuedVerifyEmail))->afterResponse();
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    public function dealRedemptions(): HasMany
    {
        return $this->hasMany(DealRedemption::class, 'customer_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    /** Sales this user rang up as the cashier — distinct from sales() (that user's own purchases). */
    public function createdSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'created_by');
    }

    public function routeNotificationForSms($notification = null): ?string
    {
        return $this->attributes['phone'] ?? null;
    }

    public function routeNotificationForWhatsapp($notification = null): ?string
    {
        return $this->attributes['phone'] ?? null;
    }

    /**
     * Identity fields only — deliberately excludes `password`/`remember_token`/`two_factor_secret`/
     * `two_factor_recovery_codes` so the audit trail never carries a hash or a secret, even
     * indirectly. That a credential changed is visible from the `updated` event itself; what it
     * changed to never needs to be.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'suspended_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('users');
    }
}
