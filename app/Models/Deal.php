<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The 6 filter tabs the public Deals page and the admin category select both draw from — a
     * single source of truth so the two can never drift into offering different tag lists.
     */
    public const CATEGORY_TAGS = [
        'Top Deals',
        'Hair Deals',
        'Makeup Deals',
        'Skin Deals',
        'Combo Deals',
        'Referral Deals',
    ];

    protected $fillable = [
        'title',
        'slug',
        'subtitle',
        'category_tag',
        'type',
        'value',
        'original_price',
        'deal_price',
        'included_services',
        'description',
        'terms',
        'image_path',
        'stock_image_url',
        'code',
        'starts_at',
        'ends_at',
        'usage_limit',
        'per_user_limit',
        'min_amount',
        'is_stackable',
        'is_auto_apply',
        'is_active',
        'is_top_deal',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'original_price' => 'decimal:2',
            'deal_price' => 'decimal:2',
            'included_services' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'min_amount' => 'decimal:2',
            'is_stackable' => 'boolean',
            'is_auto_apply' => 'boolean',
            'is_active' => 'boolean',
            'is_top_deal' => 'boolean',
        ];
    }

    /**
     * Whole-percent savings for the card badge ("33% OFF"), computed from the real display prices
     * rather than stored — so an admin editing either price can never leave a stale percentage on
     * screen. Null when either price is missing, so the UI can omit the badge rather than divide by
     * zero or show a fabricated number.
     */
    public function getSavingsPercentAttribute(): ?int
    {
        if (! $this->original_price || (float) $this->original_price <= 0 || $this->deal_price === null) {
            return null;
        }

        $original = (float) $this->original_price;
        $discounted = (float) $this->deal_price;

        return (int) round((($original - $discounted) / $original) * 100);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCategory::class, 'deal_service_category');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(DealRedemption::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
}
