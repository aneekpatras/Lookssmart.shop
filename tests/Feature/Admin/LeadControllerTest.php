<?php

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('requires crm.manage to view leads', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no crm.manage

    $this->actingAs($staff)->get('/admin/leads')->assertForbidden();
});

it('filters leads by status, source, and date range', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $matching = Lead::factory()->create(['status' => 'new', 'source' => 'contact_form', 'created_at' => now()]);
    Lead::factory()->create(['status' => 'lost', 'source' => 'contact_form', 'created_at' => now()]);
    Lead::factory()->create(['status' => 'new', 'source' => 'phone', 'created_at' => now()]);
    Lead::factory()->create(['status' => 'new', 'source' => 'contact_form', 'created_at' => now()->subDays(10)]);

    $response = $this->actingAs($admin)->get('/admin/leads?' . http_build_query([
        'status' => 'new',
        'source' => 'contact_form',
        'from' => now()->subDay()->toDateString(),
    ]));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/CRM/Leads/Index')
        ->where('leads', fn ($leads) => count($leads) === 1 && $leads[0]['id'] === $matching->id));
});

it('reports a real conversion rate computed from lead statuses', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    Lead::factory()->count(3)->create(['status' => 'new']);
    Lead::factory()->create(['status' => 'converted']);

    $this->actingAs($admin)->get('/admin/leads')->assertInertia(fn ($page) => $page
        ->where('stats.total', 4)
        ->where('stats.converted', 1)
        ->where('stats.conversion_rate', 25));
});

it('updates a lead status', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $lead = Lead::factory()->create(['status' => 'new']);

    $this->actingAs($admin)->patch("/admin/leads/{$lead->id}/status", ['status' => 'qualified'])
        ->assertRedirect();

    expect($lead->fresh()->status)->toBe('qualified');
});

it('rejects an invalid lead status', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $lead = Lead::factory()->create(['status' => 'new']);

    $this->actingAs($admin)->patch("/admin/leads/{$lead->id}/status", ['status' => 'bogus'])
        ->assertSessionHasErrors('status');

    expect($lead->fresh()->status)->toBe('new');
});

it('assigns a lead to a staff member', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $staffUser = $makeConfirmedUser('staff');
    $lead = Lead::factory()->create();

    $this->actingAs($admin)->patch("/admin/leads/{$lead->id}/assign", ['assigned_to' => $staffUser->id])
        ->assertRedirect();

    expect($lead->fresh()->assigned_to)->toBe($staffUser->id);
});

it('adds an internal note to a lead and returns it in the detail payload', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $lead = Lead::factory()->create();

    $this->actingAs($admin)->post("/admin/leads/{$lead->id}/notes", ['body' => 'Called, left voicemail.'])
        ->assertRedirect();

    expect(LeadNote::where('lead_id', $lead->id)->count())->toBe(1);

    $this->actingAs($admin)->getJson("/admin/leads/{$lead->id}")
        ->assertOk()
        ->assertJsonPath('lead.notes_timeline.0.body', 'Called, left voicemail.')
        ->assertJsonPath('lead.notes_timeline.0.author', $admin->name);
});

it('deletes a lead', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $lead = Lead::factory()->create();

    $this->actingAs($admin)->delete("/admin/leads/{$lead->id}")->assertRedirect();

    expect(Lead::find($lead->id))->toBeNull();
});
