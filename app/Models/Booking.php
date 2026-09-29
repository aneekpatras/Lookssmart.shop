<?php

namespace App\Models;

use App\Concerns\LogsAuditableActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model
{
    use HasFactory, LogsActivity, LogsAuditableActivity, SoftDeletes;

    protected $fillable = [
        'code',
        'customer_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'staff_id',
        'starts_at',
        'ends_at',
        'status',
        'source',
        'total',
        'discount',
        'tax',
        'notes',
        'cancellation_reason',
        'reminded_at',
        'calendar_event_id',
    ];

    protected function casts(): array
    {
        return [
            'guest_email' => 'encrypted',
            'guest_phone' => 'encrypted',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'total' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'reminded_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(BookingStatusLog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function dealRedemptions(): HasMany
    {
        return $this->hasMany(DealRedemption::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now())
            ->whereNotIn('status', ['cancelled', 'no_show']);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * The single source of truth for the 24h (admin-configurable via `booking.cancellation_window_hours`)
     * free-cancellation policy — previously duplicated inline, identically, in both
     * `CancelBookingAction` and `RescheduleBookingAction`. Also drives the customer-facing "My
     * Bookings" page's fee-warning modal, so the frontend's judgment of "is this a free cancellation"
     * can never drift from what the backend will actually accept.
     */
    public function isWithinCancellationWindow(): bool
    {
        $windowHours = (int) (Setting::get('booking.cancellation_window_hours', 24) ?? 24);

        return now()->diffInHours($this->starts_at, false) < $windowHours;
    }

    /**
     * Whether a customer (not an admin, who can always bypass the window) could cancel this booking
     * online right now — status still active/manageable AND outside the free-cancellation window.
     */
    public function isCustomerCancellable(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'starts_at', 'ends_at', 'staff_id', 'total', 'cancellation_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('bookings');
    }
}
