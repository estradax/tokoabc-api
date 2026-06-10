<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('unauthenticated user cannot access customer routes', function () {
    $this->getJson('/api/customers')->assertUnauthorized();
    $this->postJson('/api/customers', [])->assertUnauthorized();
});

test('authenticated user can list customers with pagination', function () {
    $user = User::factory()->create();
    Customer::factory()->count(20)->create();

    $response = $this->actingAs($user)
        ->getJson('/api/customers');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'last_page',
                'total',
            ],
        ]);

    expect($response->json('data'))->toHaveCount(15);
});

test('authenticated user can search customers', function () {
    $user = User::factory()->create();
    Customer::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
    Customer::factory()->create([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/customers?search=John');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('John Doe');
});

test('authenticated user can create a customer', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/customers', [
            'name' => 'Alice Cooper',
            'email' => 'alice@example.com',
            'phone' => '1234567890',
            'address' => '123 Wonderland St',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'customer' => [
                'id',
                'name',
                'email',
                'phone',
                'address',
            ],
        ]);

    $this->assertDatabaseHas('customers', [
        'name' => 'Alice Cooper',
        'email' => 'alice@example.com',
    ]);
});

test('authenticated user can update a customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'name' => 'Bob Marley',
        'email' => 'bob@example.com',
    ]);

    $response = $this->actingAs($user)
        ->putJson("/api/customers/{$customer->id}", [
            'name' => 'Bob Marley Updated',
            'email' => 'bob.updated@example.com',
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => 'Bob Marley Updated',
        'email' => 'bob.updated@example.com',
    ]);
});

test('authenticated user can delete a customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($user)
        ->deleteJson("/api/customers/{$customer->id}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('customers', [
        'id' => $customer->id,
    ]);
});
