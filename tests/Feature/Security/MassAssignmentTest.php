<?php

use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('throws instead of silently discarding a mass-assigned non-fillable attribute', function () {
    // `AppServiceProvider::boot()` calls `Model::preventSilentlyDiscardingAttributes(true)` outside
    // production (Phase 4 sub-step 5) — this is what's actually under test, not just $fillable itself.
    expect(fn () => User::create([
        'name' => 'Mass Assignment Test',
        'email' => 'mass-assignment-test@example.com',
        'password' => 'a-very-strong-password-123',
        'is_super_admin' => true, // not in User::$fillable, and not even a real column
    ]))->toThrow(MassAssignmentException::class);
});

it('still allows normal fillable attributes through', function () {
    $user = User::create([
        'name' => 'Mass Assignment Test 2',
        'email' => 'mass-assignment-test-2@example.com',
        'password' => 'a-very-strong-password-123',
    ]);

    expect($user->exists)->toBeTrue()
        ->and($user->email)->toBe('mass-assignment-test-2@example.com');
});
