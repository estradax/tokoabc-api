<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('unauthenticated user cannot access order routes', function () {
    $this->getJson('/api/orders')->assertUnauthorized();
});

test('authenticated user can list orders with pagination', function () {
    $user = User::factory()->create();
    Order::factory()->count(20)->create();

    $response = $this->actingAs($user)
        ->getJson('/api/orders');

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

test('authenticated user can search orders by customer name', function () {
    $user = User::factory()->create();
    $customer1 = Customer::factory()->create(['name' => 'John Doe']);
    $customer2 = Customer::factory()->create(['name' => 'Jane Smith']);

    Order::factory()->create(['customer_id' => $customer1->id]);
    Order::factory()->create(['customer_id' => $customer2->id]);

    $response = $this->actingAs($user)
        ->getJson('/api/orders?search=John');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
});

test('authenticated user can filter orders by status', function () {
    $user = User::factory()->create();
    Order::factory()->create(['status' => 'pending']);
    Order::factory()->create(['status' => 'completed']);
    Order::factory()->create(['status' => 'cancelled']);

    $response = $this->actingAs($user)
        ->getJson('/api/orders?status=completed');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('completed');
});

test('authenticated user can view a single order details', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create();
    OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

    $response = $this->actingAs($user)
        ->getJson("/api/orders/{$order->id}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'id',
            'order_number',
            'total_amount',
            'status',
            'customer' => [
                'id',
                'name',
                'email',
            ],
            'items' => [
                '*' => [
                    'id',
                    'product_id',
                    'quantity',
                    'unit_price',
                    'product_json',
                    'product',
                ],
            ],
        ]);
});

test('authenticated user can update order status', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => 'pending']);

    $response = $this->actingAs($user)
        ->putJson("/api/orders/{$order->id}", [
            'status' => 'completed',
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'completed',
    ]);
});
