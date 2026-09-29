<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'gallery_id',
        'image_path',
        'caption',
        'is_before_after',
        'show_on_homepage',
        'pair_image_path',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'is_before_after' => 'boolean',
            'show_on_homepage' => 'boolean',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Images an admin has enabled for the homepage bridal slider. Only images in an ACTIVE album
     * qualify — deactivating an album should pull its photos off the homepage too, rather than
     * leaving them on the busiest page of the site after they were deliberately hidden everywhere
     * else.
     */
    public function scopeForHomepageSlider($query)
    {
        return $query
            ->where('show_on_homepage', true)
            ->whereHas('gallery', fn ($gallery) => $gallery->where('is_active', true));
    }
}
