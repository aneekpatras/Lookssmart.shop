<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterShift extends Model
{
    protected $fillable = [
        'staff_id',
        'opening_float',
        'closing_float',
        'expected_total',
        'counted_total',
        'variance',
        'status',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_float' => 'decimal:2',
            'closing_float' => 'decimal:2',
            'expected_total' => 'decimal:2',
            'counted_total' => 'decimal:2',
            'variance' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
