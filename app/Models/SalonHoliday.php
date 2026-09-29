<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalonHoliday extends Model
{
    protected $fillable = [
        'name',
        'starts_at',
        'ends_at',
        'is_recurring_yearly',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_recurring_yearly' => 'boolean',
        ];
    }
}
