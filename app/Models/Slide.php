<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Slide extends Model
{
    use HasFactory;

    protected $fillable = [
        'slider_id',
        'heading',
        'subheading',
        'image_path',
        'mobile_image_path',
        'cta_text',
        'cta_url',
        'text_position',
        'animation',
        'overlay_opacity',
        'sort',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'overlay_opacity' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function slider(): BelongsTo
    {
        return $this->belongsTo(Slider::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
