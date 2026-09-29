<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'dob',
        'gender',
        'preferences',
        'marketing_opt_in',
        'email_opt_out',
        'sms_opt_out',
        'whatsapp_opt_out',
        'consented_at',
        'notes',
        'total_spent',
        'visits',
        'tags',
        'loyalty_points',
        'no_show_count',
        'is_blacklisted',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'preferences' => 'array',
            'marketing_opt_in' => 'boolean',
            'email_opt_out' => 'boolean',
            'sms_opt_out' => 'boolean',
            'whatsapp_opt_out' => 'boolean',
            'consented_at' => 'datetime',
            'notes' => 'encrypted',
            'total_spent' => 'decimal:2',
            'tags' => 'array',
            'is_blacklisted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBlacklisted($query)
    {
        return $query->where('is_blacklisted', true);
    }
}
