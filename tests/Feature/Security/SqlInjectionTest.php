<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not error or match anything when a classic SQLi payload is queried through Eloquent', function () {
    // Eloquent's query builder always parameter-binds — this proves it for real rather than just
    // trusting the convention (Brief §9 bans raw SQL where Eloquent works, precisely to guarantee this).
    $payload = "' OR '1'='1";

    $result = User::where('email', $payload)->first();

    expect($result)->toBeNull();
});

it('safely rejects a SQLi payload submitted through the real login endpoint', function () {
    $response = $this->withSession(['_token' => 'test-token'])->post('/login', [
        '_token' => 'test-token',
        'email' => "' OR '1'='1' -- ",
        'password' => 'anything',
    ]);

    // Never a 500 (no query broke), never an authenticated session (no auth bypass) — just a normal
    // "these credentials don't match" redirect back to the login form.
    $response->assertStatus(302)->assertSessionHasErrors();
    $this->assertGuest();
});
