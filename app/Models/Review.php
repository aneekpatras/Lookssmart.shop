<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The 4 service categories the public "Write a Review" modal offers — single source of truth
     * shared by `SubmitReviewRequest`'s validation and the modal's own category chips/templates, so
     * the two can never drift into offering different lists.
     */
    public const WRITE_REVIEW_CATEGORIES = [
        'Hair Treatments',
        'Facials & Skin Care',
        'Bridal & Makeup',
        'Laser Hair Removal',
    ];

    protected $fillable = [
        'customer_id',
        'reviewer_name',
        'booking_id',
        'service_id',
        'reviewer_category',
        'rating',
        'title',
        'body',
        'status',
        'source',
        'admin_reply',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Display name for a review card: a Google review's real `reviewer_name` (there is no linked
     * `User`), falling back to an organic review's linked customer, then a generic label — never
     * blank.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->reviewer_name ?? $this->customer?->name ?? 'Client';
    }

    /**
     * Treatment-category label for a review card: a Google review's own `reviewer_category` (it has
     * no linked `Service`), falling back to an organic review's real service's category name.
     */
    public function getDisplayCategoryAttribute(): ?string
    {
        return $this->reviewer_category ?? $this->service?->category?->name;
    }
}
