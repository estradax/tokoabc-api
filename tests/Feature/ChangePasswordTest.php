<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

test('an authenticated user can change their password', function () {
    $user = User::factory()->create([
        'password' => 'old-password123',
    ]);
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/password', [
            'current_password' => 'old-password123',
            'password' => 'new-password456',
            'password_confirmation' => 'new-password456',
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Password changed successfully',
        ]);

    $user->refresh();
    expect(Hash::check('new-password456', $user->password))->toBeTrue();
});

test('an authenticated user cannot change password with wrong current password', function () {
    $user = User::factory()->create([
        'password' => 'old-password123',
    ]);
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password456',
            'password_confirmation' => 'new-password456',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});

test('an authenticated user cannot change password without matching confirmation', function () {
    $user = User::factory()->create([
        'password' => 'old-password123',
    ]);
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/password', [
            'current_password' => 'old-password123',
            'password' => 'new-password456',
            'password_confirmation' => 'mismatched-password',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('an unauthenticated user cannot change password', function () {
    $response = $this->putJson('/api/password', [
        'current_password' => 'old-password123',
        'password' => 'new-password456',
        'password_confirmation' => 'new-password456',
    ]);

    $response->assertStatus(401);
});
