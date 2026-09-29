<?php

use App\Models\Booking;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\ReviewStatusUpdated;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

$makeConfirmedUser = function (string $role): User {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
};

it('requires crm.manage to view the moderation queue', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no crm.manage

    $this->actingAs($staff)->get('/admin/reviews')->assertForbidden();
});

it('filters reviews by status, rating, and verified booking', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id]);
    $staffMember = Staff::factory()->create();
    $booking = Booking::factory()->create(['staff_id' => $staffMember->id]);

    $matching = Review::factory()->create([
        'status' => 'pending',
        'rating' => 5,
        'booking_id' => $booking->id,
        'service_id' => $service->id,
    ]);
    Review::factory()->create(['status' => 'approved', 'rating' => 5, 'booking_id' => $booking->id]);
    Review::factory()->create(['status' => 'pending', 'rating' => 3, 'booking_id' => $booking->id]);
    Review::factory()->create(['status' => 'pending', 'rating' => 5, 'booking_id' => null]);

    $response = $this->actingAs($admin)->get('/admin/reviews?' . http_build_query([
        'status' => 'pending',
        'rating' => 5,
        'verified' => 'true',
    ]));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/CRM/Reviews/Index')
        ->where('reviews', fn ($reviews) => count($reviews) === 1 && $reviews[0]['id'] === $matching->id));
});

it('approves a review, sets published_at, and notifies the customer', function () use ($makeConfirmedUser) {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $customer = User::factory()->create();
    $review = Review::factory()->create(['status' => 'pending', 'customer_id' => $customer->id, 'published_at' => null]);

    $this->actingAs($admin)->patch("/admin/reviews/{$review->id}/approve")->assertRedirect();

    expect($review->fresh()->status)->toBe('approved')
        ->and($review->fresh()->published_at)->not->toBeNull();

    Notification::assertSentTo($customer, ReviewStatusUpdated::class);
});

it('rejects a review without notifying the customer', function () use ($makeConfirmedUser) {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $customer = User::factory()->create();
    $review = Review::factory()->create(['status' => 'pending', 'customer_id' => $customer->id]);

    $this->actingAs($admin)->patch("/admin/reviews/{$review->id}/reject")->assertRedirect();

    expect($review->fresh()->status)->toBe('rejected');
    Notification::assertNotSentTo($customer, ReviewStatusUpdated::class);
});

it('saves a public salon response and notifies the customer', function () use ($makeConfirmedUser) {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $customer = User::factory()->create();
    $review = Review::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)->post("/admin/reviews/{$review->id}/reply", [
        'admin_reply' => 'Thank you so much for your kind words!',
    ])->assertRedirect();

    expect($review->fresh()->admin_reply)->toBe('Thank you so much for your kind words!');
    Notification::assertSentTo($customer, ReviewStatusUpdated::class);
});

it('does not notify for a guest review with no registered customer', function () use ($makeConfirmedUser) {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $review = Review::factory()->create(['customer_id' => null]);

    $this->actingAs($admin)->patch("/admin/reviews/{$review->id}/approve")->assertRedirect();

    expect($review->fresh()->status)->toBe('approved');
    Notification::assertNothingSent();
});

it('applies a bulk approve action across multiple reviews', function () use ($makeConfirmedUser) {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $first = Review::factory()->create(['status' => 'pending']);
    $second = Review::factory()->create(['status' => 'pending']);
    $untouched = Review::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)->post('/admin/reviews/bulk', [
        'ids' => [$first->id, $second->id],
        'action' => 'approve',
    ])->assertRedirect();

    expect($first->fresh()->status)->toBe('approved')
        ->and($second->fresh()->status)->toBe('approved')
        ->and($untouched->fresh()->status)->toBe('pending');
});

it('rejects an unknown bulk action', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $review = Review::factory()->create();

    $this->actingAs($admin)->post('/admin/reviews/bulk', [
        'ids' => [$review->id],
        'action' => 'not-a-real-action',
    ])->assertSessionHasErrors('action');
});

it('deletes a review', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $review = Review::factory()->create();

    $this->actingAs($admin)->delete("/admin/reviews/{$review->id}")->assertRedirect();

    expect(Review::find($review->id))->toBeNull();
});
