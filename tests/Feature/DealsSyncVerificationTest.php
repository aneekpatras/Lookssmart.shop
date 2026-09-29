<?php

use App\Models\CashRegisterShift;
use App\Models\Deal;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A direct, end-to-end check of the exact claim under investigation ("a deal created in
 * /admin/deals/create doesn't show up on /deals or in POS") — created through the REAL admin HTTP
 * endpoint (not a factory/direct Eloquent insert), then read back through both real read paths, in
 * the same test run, to rule out any caching/staleness rather than assume it away.
 */
it('shows a deal created through the real admin endpoint on the public deals page immediately, with no caching gap', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    $category = ServiceCategory::factory()->create();

    $this->actingAs($admin)->post('/admin/deals', [
        'title' => 'Brand New Hair Deal',
        'type' => 'percent',
        'value' => 15,
        'code' => 'NEWHAIR15',
        'category_tag' => 'Hair Deals',
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDays(7),
        'category_ids' => [$category->id],
    ])->assertRedirect(route('admin.deals'));

    $this->get('/deals')->assertInertia(fn ($page) => $page
        ->has('sections', 1, fn ($section) => $section
            ->where('tag', 'Hair Deals')
            ->has('deals', 1, fn ($deal) => $deal->where('title', 'Brand New Hair Deal')->etc())
            ->etc()));
});

it('surfaces that same freshly-created deal through the POS search endpoint immediately', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    CashRegisterShift::create([
        'staff_id' => $admin->id, 'opening_float' => 100, 'status' => 'open', 'opened_at' => now(),
    ]);

    $this->actingAs($admin)->post('/admin/deals', [
        'title' => 'Brand New POS Deal',
        'type' => 'percent',
        'value' => 10,
        'code' => 'NEWPOS10',
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDays(7),
    ])->assertRedirect(route('admin.deals'));

    $this->actingAs($admin)->getJson('/admin/pos/search/deals?q=Brand New POS Deal')
        ->assertOk()
        ->assertJsonPath('deals.0.title', 'Brand New POS Deal');
});

/**
 * The documented, deliberate behavior (see PublicWebsiteController::deals()'s own docblock): a deal
 * with NO `category_tag` is an admin-only/homepage-banner-only discount and correctly never appears
 * on /deals. Asserted explicitly so this can never be mistaken for the bug above.
 */
it('correctly hides a deal with no category_tag from the public deals page, by design', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Deal::factory()->create([
        'title' => 'Untagged Admin-Only Deal',
        'category_tag' => null,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $this->get('/deals')->assertInertia(fn ($page) => $page
        ->where('sections', fn ($sections) => collect($sections)->pluck('deals')->flatten(1)
            ->pluck('title')->doesntContain('Untagged Admin-Only Deal')));
});
