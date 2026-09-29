<?php

use App\Models\BusinessHour;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

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

it('allows settings.view to see the settings page but not to write', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin'); // settings.view only, per Brief §2

    $this->actingAs($admin)->get('/admin/settings')->assertOk();

    $response = $this->actingAs($admin)->put('/admin/settings/business', [
        'settings' => ['business.name' => 'New Name'],
    ]);

    $response->assertForbidden();
});

it('requires settings.manage (super-admin) to write settings', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no settings permission at all

    $this->actingAs($staff)->get('/admin/settings')->assertForbidden();
});

it('updates business settings, busts the cache, and re-populates it on next read', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedUser('super-admin');
    Setting::create(['key' => 'business.name', 'value' => 'Old Name', 'group' => 'business']);

    // Warm the cache the way Setting::get() does.
    expect(Setting::get('business.name'))->toBe('Old Name');
    expect(Cache::has('setting:business.name'))->toBeTrue();

    $response = $this->actingAs($superAdmin)->put('/admin/settings/business', [
        'settings' => [
            'business.name' => 'Looks Smart Salon & Spa',
            'business.phone' => '+1 555 0100',
        ],
    ]);

    $response->assertRedirect();
    expect(Cache::has('setting:business.name'))->toBeFalse(); // busted by the write
    expect(Setting::get('business.name'))->toBe('Looks Smart Salon & Spa'); // re-populates on next read
    expect(Setting::where('key', 'business.phone')->first()?->value)->toBe('+1 555 0100');
});

it('encrypts a secret setting and masks it back to the UI, and leaving it blank keeps the existing value', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedUser('super-admin');

    $this->actingAs($superAdmin)->put('/admin/settings/integrations', [
        'settings' => ['integrations.twilio_sid' => 'AC1234567890'],
    ])->assertRedirect();

    $stored = Setting::where('key', 'integrations.twilio_sid')->first();
    expect($stored->value)->not->toBe('AC1234567890') // stored ciphertext, not plaintext
        ->and($stored->is_encrypted)->toBeTrue();

    $settingService = app(SettingService::class);
    expect($settingService->get('integrations.twilio_sid'))->toBe('AC1234567890')
        ->and($settingService->maskedGroup('integrations')['integrations.twilio_sid'])->toBe(SettingService::MASK);

    // Submitting blank for the secret leaves the existing value untouched.
    $this->actingAs($superAdmin)->put('/admin/settings/integrations', [
        'settings' => ['integrations.twilio_sid' => ''],
    ])->assertRedirect();

    expect($settingService->get('integrations.twilio_sid'))->toBe('AC1234567890');
});

it('rejects an invalid payload with a real per-field validation error', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedUser('super-admin');

    $response = $this->actingAs($superAdmin)->put('/admin/settings/booking', [
        'settings' => ['booking.slot_minutes' => 'not-a-number'],
    ]);

    $response->assertSessionHasErrors('settings.booking.slot_minutes');
});

it('rejects a settings key that does not belong to the requested group', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedUser('super-admin');

    $response = $this->actingAs($superAdmin)->put('/admin/settings/business', [
        'settings' => ['booking.slot_minutes' => 30],
    ]);

    $response->assertSessionHasErrors('settings.booking.slot_minutes');
});

it('rejects malformed GA4/GTM ids and accepts real-shaped ones', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedUser('super-admin');

    // Phase 13 sub-step 2: these 2 get embedded directly into an inline <script> template literal
    // (SeoHead.tsx) — format validation isn't just UX, it keeps a malformed value from ever being
    // able to break out of that string.
    $this->actingAs($superAdmin)->put('/admin/settings/integrations', [
        'settings' => ['integrations.google_analytics_id' => "G-ABC'; alert(1); //"],
    ])->assertSessionHasErrors('settings.integrations.google_analytics_id');

    $this->actingAs($superAdmin)->put('/admin/settings/integrations', [
        'settings' => ['integrations.google_tag_manager_id' => 'not-a-gtm-id'],
    ])->assertSessionHasErrors('settings.integrations.google_tag_manager_id');

    $this->actingAs($superAdmin)->put('/admin/settings/integrations', [
        'settings' => [
            'integrations.google_analytics_id' => 'G-ABCDEF1234',
            'integrations.google_tag_manager_id' => 'GTM-ABC1234',
        ],
    ])->assertSessionDoesntHaveErrors([
        'settings.integrations.google_analytics_id',
        'settings.integrations.google_tag_manager_id',
    ]);

    expect(Setting::get('integrations.google_analytics_id'))->toBe('G-ABCDEF1234')
        ->and(Setting::get('integrations.google_tag_manager_id'))->toBe('GTM-ABC1234');
});

it('updates operating hours', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedUser('super-admin');
    $hours = collect(range(0, 6))->map(fn (int $weekday) => [
        'weekday' => $weekday,
        'is_closed' => $weekday === 0,
        'open_time' => $weekday === 0 ? null : '10:00',
        'close_time' => $weekday === 0 ? null : '18:00',
    ])->all();

    $this->actingAs($superAdmin)->put('/admin/settings/hours', ['hours' => $hours])->assertRedirect();

    $sunday = BusinessHour::where('weekday', 0)->first();
    $monday = BusinessHour::where('weekday', 1)->first();
    expect($sunday->is_closed)->toBeTrue()
        ->and($sunday->open_time)->toBeNull()
        ->and($monday->open_time)->toBe('10:00')
        ->and($monday->close_time)->toBe('18:00');
});
