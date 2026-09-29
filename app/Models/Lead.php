<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'source',
        'status',
        'assigned_to',
        'notes',
        'converted_booking_id',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'converted_booking_id');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
