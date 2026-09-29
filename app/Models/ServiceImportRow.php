<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceImportRow extends Model
{
    protected $fillable = [
        'service_import_id',
        'row_number',
        'action',
        'matched_service_id',
        'data',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'errors' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ServiceImport::class, 'service_import_id');
    }

    public function matchedService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'matched_service_id');
    }
}
