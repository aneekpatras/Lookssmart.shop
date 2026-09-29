<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceImport extends Model
{
    protected $fillable = [
        'original_filename',
        'file_path',
        'status',
        'total_rows',
        'create_count',
        'update_count',
        'error_count',
        'failure_reason',
        'created_by',
        'previewed_at',
        'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'previewed_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ServiceImportRow::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
