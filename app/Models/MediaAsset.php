<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeSearch($query, ?string $term)
    {
        return $query->when($term, fn ($query) => $query->where(function ($query) use ($term) {
            $query->where('original_name', 'like', "%{$term}%")->orWhere('path', 'like', "%{$term}%");
        }));
    }

    public function scopeOfType($query, ?string $type)
    {
        return $query->when($type === 'images', fn ($query) => $query->where('mime_type', 'like', 'image/%'))
            ->when($type === 'documents', fn ($query) => $query->where('mime_type', 'not like', 'image/%'));
    }
}
