<?php

/**
 * Automates what Phase 4 sub-step 1 verified manually via `curl` (both `register` and
 * `password-reset` hit their configured 429 within a handful of attempts, with a minor,
 * already-documented off-by-one on `register` — see 02-PROJECT-STATE.md §3). This turns that
 * one-time manual check into a permanent regression test.
 */
it('rate limits repeated registration attempts from the same IP', function () {
    $blocked = false;

    for ($i = 0; $i < 6; $i++) {
        $response = $this->withSession(['_token' => 'test-token'])->post('/register', [
            '_token' => 'test-token',
            'name' => "Rate Limit Test {$i}",
            'email' => "ratelimit{$i}@example.com",
            'password' => 'a-very-strong-password-123',
            'password_confirmation' => 'a-very-strong-password-123',
        ]);

        if ($response->getStatusCode() === 429) {
            $blocked = true;
            break;
        }
    }

    expect($blocked)->toBeTrue();
});

it('rate limits repeated password-reset requests from the same IP+email', function () {
    $blocked = false;

    for ($i = 0; $i < 6; $i++) {
        $response = $this->withSession(['_token' => 'test-token'])->post('/forgot-password', [
            '_token' => 'test-token',
            'email' => 'someone@example.com',
        ]);

        if ($response->getStatusCode() === 429) {
            $blocked = true;
            break;
        }
    }

    expect($blocked)->toBeTrue();
});
