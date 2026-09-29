<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePriceHistory extends Model
{
    const UPDATED_AT = null;

    protected $table = 'service_price_history';

    protected $fillable = [
        'service_id',
        'service_price_id',
        'price_list',
        'old_price',
        'new_price',
        'changed_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
