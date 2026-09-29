<?php

use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sends up to 12 approved and published reviews to the homepage, organic and Google alike', function () {
    $category = ServiceCategory::factory()->create(['name' => 'Hair Styling & Treatments']);
    $service = Service::factory()->create(['service_category_id' => $category->id, 'is_active' => true]);
    $customer = User::factory()->create(['name' => 'Amara Khan']);

    Review::factory()->create([
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'rating' => 5,
        'status' => 'approved',
        'published_at' => now()->subDay(),
        'source' => 'organic',
    ]);

    Review::factory()->create([
        'customer_id' => null,
        'service_id' => null,
        'reviewer_name' => 'Amna Riaz',
        'reviewer_category' => 'Hair Treatments',
        'rating' => 5,
        'body' => 'Extremely impressed with the Hair Botox treatment here!',
        'status' => 'approved',
        'published_at' => now(),
        'source' => 'google',
    ]);

    // Not shown: pending status, and published_at null (approved but never surfaced).
    Review::factory()->create(['status' => 'pending', 'published_at' => now()]);
    Review::factory()->create(['status' => 'approved', 'published_at' => null]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->component('Public/Home')
        ->has('testimonials', 2)
        ->where('testimonials.0.name', 'Amna Riaz')
        ->where('testimonials.0.category', 'Hair Treatments')
        ->where('testimonials.0.source', 'google')
        ->where('testimonials.1.name', 'Amara Khan')
        ->where('testimonials.1.category', 'Hair Styling & Treatments')
        ->where('testimonials.1.source', 'organic'));
});

it('caps testimonials at 12 even when more approved reviews exist', function () {
    Review::factory()->count(15)->create([
        'status' => 'approved',
        'published_at' => now(),
        'reviewer_name' => 'Seed Reviewer',
    ]);

    $this->get('/')->assertInertia(fn ($page) => $page->has('testimonials', 12));
});

it("a Google review's display name and category use its own reviewer fields, not a linked customer/service", function () {
    $review = Review::factory()->create([
        'customer_id' => null,
        'service_id' => null,
        'reviewer_name' => 'Sana Malik',
        'reviewer_category' => 'Hair Treatments',
        'source' => 'google',
    ]);

    expect($review->fresh()->display_name)->toBe('Sana Malik')
        ->and($review->fresh()->display_category)->toBe('Hair Treatments');
});

it("an organic review's display name and category fall back to its linked customer and service category", function () {
    $category = ServiceCategory::factory()->create(['name' => 'Skin & Facial Care']);
    $service = Service::factory()->create(['service_category_id' => $category->id]);
    $customer = User::factory()->create(['name' => 'Zoya Ahmed']);

    $review = Review::factory()->create([
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'reviewer_name' => null,
        'reviewer_category' => null,
        'source' => 'organic',
    ]);

    expect($review->fresh()->display_name)->toBe('Zoya Ahmed')
        ->and($review->fresh()->display_category)->toBe('Skin & Facial Care');
});

it('falls back to a generic "Client" display name when a review has neither a reviewer_name nor a linked customer', function () {
    $review = Review::factory()->create(['customer_id' => null, 'reviewer_name' => null]);

    expect($review->fresh()->display_name)->toBe('Client');
});

it('defaults every new review to the organic source unless explicitly seeded as google', function () {
    $review = Review::factory()->create();

    expect($review->fresh()->source)->toBe('organic');
});
