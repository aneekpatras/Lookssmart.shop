<?php

use App\Models\Message;
use App\Models\MessageReply;
use App\Models\User;
use App\Notifications\MessageReply as MessageReplyNotification;
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

it('requires crm.manage to view messages', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no crm.manage

    $this->actingAs($staff)->get('/admin/messages')->assertForbidden();
});

it('flags a message older than 24h with no reply as overdue', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $overdue = Message::factory()->create(['status' => 'unread', 'created_at' => now()->subHours(30)]);
    Message::factory()->create(['status' => 'unread', 'created_at' => now()->subHours(2)]);
    Message::factory()->create(['status' => 'replied', 'created_at' => now()->subHours(30), 'replied_at' => now()]);

    $response = $this->actingAs($admin)->get('/admin/messages');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('stats.overdue', 1)
        ->where('messages', fn ($messages) => collect($messages)->firstWhere('id', $overdue->id)['is_overdue'] === true));
});

it('marks a message as read and returns the thread when opened', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $message = Message::factory()->create(['status' => 'unread']);

    $response = $this->actingAs($admin)->getJson("/admin/messages/{$message->id}");

    $response->assertOk()->assertJsonPath('message.status', 'read');
    expect($message->fresh()->status)->toBe('read');
});

it('dispatches a real reply notification, records the thread, and marks the message replied', function () use ($makeConfirmedUser) {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $message = Message::factory()->create(['status' => 'unread', 'email' => 'lead@example.com']);

    $response = $this->actingAs($admin)->post("/admin/messages/{$message->id}/reply", [
        'body' => 'Thanks for reaching out, here is the info you asked for.',
    ]);

    $response->assertRedirect();
    expect($message->fresh()->status)->toBe('replied')
        ->and($message->fresh()->replied_at)->not->toBeNull()
        ->and(MessageReply::where('message_id', $message->id)->count())->toBe(1)
        ->and(MessageReply::where('message_id', $message->id)->first()->body)->toContain('Thanks for reaching out');

    Notification::assertSentOnDemand(
        MessageReplyNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'lead@example.com',
    );
});

it('toggles a message spam status', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $message = Message::factory()->create(['status' => 'read']);

    $this->actingAs($admin)->patch("/admin/messages/{$message->id}/spam")->assertRedirect();
    expect($message->fresh()->status)->toBe('spam');

    $this->actingAs($admin)->patch("/admin/messages/{$message->id}/spam")->assertRedirect();
    expect($message->fresh()->status)->toBe('read');
});

it('applies a bulk action across multiple messages', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $first = Message::factory()->create(['status' => 'unread']);
    $second = Message::factory()->create(['status' => 'unread']);
    $untouched = Message::factory()->create(['status' => 'unread']);

    $this->actingAs($admin)->post('/admin/messages/bulk', [
        'ids' => [$first->id, $second->id],
        'action' => 'archive',
    ])->assertRedirect();

    expect($first->fresh()->archived_at)->not->toBeNull()
        ->and($second->fresh()->archived_at)->not->toBeNull()
        ->and($untouched->fresh()->archived_at)->toBeNull();
});

it('rejects an unknown bulk action', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $message = Message::factory()->create();

    $this->actingAs($admin)->post('/admin/messages/bulk', [
        'ids' => [$message->id],
        'action' => 'not-a-real-action',
    ])->assertSessionHasErrors('action');
});

it('deletes a message', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $message = Message::factory()->create();

    $this->actingAs($admin)->delete("/admin/messages/{$message->id}")->assertRedirect();

    expect(Message::find($message->id))->toBeNull();
});
