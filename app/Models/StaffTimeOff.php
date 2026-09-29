<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTimeOff extends Model
{
    // The migration creates `staff_time_off` (singular) — Eloquent's convention would otherwise guess
    // `staff_time_offs`, which doesn't exist. Pre-existing mismatch, never hit until this phase
    // actually queried the model instead of just declaring a Policy for it.
    protected $table = 'staff_time_off';

    protected $fillable = [
        'staff_id',
        'starts_at',
        'ends_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
