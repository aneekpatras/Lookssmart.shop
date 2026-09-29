<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderRule extends Model
{
    protected $fillable = [
        'event',
        'direction',
        'offset_minutes',
        'channels',
        'template',
        'is_active',
        'target_filters',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'is_active' => 'boolean',
            'target_filters' => 'array',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
