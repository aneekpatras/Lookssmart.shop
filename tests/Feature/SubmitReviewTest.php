<?php

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function validReviewPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'A Real Guest',
        'rating' => 5,
        'category' => 'Hair Treatments',
        'body' => 'A genuinely great cut and color, will be back again soon!',
        'website' => '',
        'rendered_at' => now()->subSeconds(5)->timestamp,
    ], $overrides);
}

it('saves a guest-submitted review immediately as approved and published, and returns it for the carousel', function () {
    $response = $this->postJson('/reviews', validReviewPayload());

    $response->assertOk();
    $response->assertJson(fn ($json) => $json
        ->where('ok', true)
        ->where('review.name', 'A Real Guest')
        ->where('review.category', 'Hair Treatments')
        ->where('review.rating', 5)
        ->where('review.source', 'organic')
        ->etc());

    $review = Review::where('reviewer_name', 'A Real Guest')->first();
    expect($review)->not->toBeNull()
        ->and($review->status)->toBe('approved')
        ->and($review->published_at)->not->toBeNull()
        ->and($review->customer_id)->toBeNull()
        ->and($review->source)->toBe('organic');
});

it('links the review to the real signed-in customer and ignores a supplied name in favor of their account name', function () {
    $customer = User::factory()->create(['name' => 'Real Signed-In Customer']);

    $this->actingAs($customer)
        ->postJson('/reviews', validReviewPayload(['name' => 'Someone Else Entirely']))
        ->assertOk();

    $review = Review::where('customer_id', $customer->id)->first();
    expect($review)->not->toBeNull()
        ->and($review->reviewer_name)->toBeNull()
        ->and($review->display_name)->toBe('Real Signed-In Customer');
});

it('defaults a guest who leaves the name field blank to "Verified Guest", not a validation error', function () {
    $response = $this->postJson('/reviews', validReviewPayload(['name' => '']));

    $response->assertOk();
    $response->assertJson(fn ($json) => $json->where('review.name', 'Verified Guest')->etc());

    $review = Review::first();
    expect($review->reviewer_name)->toBe('Verified Guest');
});

it('never requires a name field at all, for either a guest or a signed-in customer', function () {
    $this->postJson('/reviews', validReviewPayload(['name' => null]))->assertOk();

    $customer = User::factory()->create();
    $this->actingAs($customer)
        ->postJson('/reviews', validReviewPayload(['name' => '']))
        ->assertOk();
});

it('rejects a rating outside 1-5 and a category outside the 4 fixed write-review options', function () {
    $this->postJson('/reviews', validReviewPayload(['rating' => 6]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rating');

    $this->postJson('/reviews', validReviewPayload(['category' => 'Not A Real Category']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category');
});

it('silently drops a submission that fails the honeypot check, without creating a review', function () {
    $response = $this->postJson('/reviews', validReviewPayload(['website' => 'http://spam.example']));

    $response->assertOk();
    $response->assertJson(['ok' => true, 'review' => null]);
    expect(Review::count())->toBe(0);
});

it('silently drops a submission that fails the time-trap check, without creating a review', function () {
    $response = $this->postJson('/reviews', validReviewPayload(['rendered_at' => now()->timestamp]));

    $response->assertOk();
    $response->assertJson(['ok' => true, 'review' => null]);
    expect(Review::count())->toBe(0);
});

it('rate limits repeated review submissions from the same IP', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/reviews', validReviewPayload())->assertOk();
    }

    $this->postJson('/reviews', validReviewPayload())->assertStatus(429);
});
