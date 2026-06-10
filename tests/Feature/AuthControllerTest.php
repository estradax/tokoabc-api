<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('a user can log in with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'token',
            'user' => [
                'id',
                'name',
                'email',
                'created_at',
                'updated_at',
            ],
        ]);
});

test('a user cannot log in with incorrect credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('an authenticated user can log out', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/logout');

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Logged out successfully',
        ]);
});
