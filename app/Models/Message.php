<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'body',
        'ip',
        'status',
        'replied_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function replies(): HasMany
    {
        return $this->hasMany(MessageReply::class)->latest();
    }

    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotIn('status', ['replied', 'spam'])
            ->where('created_at', '<=', now()->subHours(24));
    }

    public function isOverdue(): bool
    {
        return ! in_array($this->status, ['replied', 'spam'], true)
            && $this->created_at?->lte(now()->subHours(24));
    }
}
