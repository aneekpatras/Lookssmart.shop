<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'channel',
        'recipient',
        'notifiable_type',
        'notifiable_id',
        'status',
        'provider_ref',
        'reminder_key',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
