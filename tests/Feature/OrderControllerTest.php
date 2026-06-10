<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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

test('authenticated user can create order with optional customer and products', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();
    $product1 = Product::factory()->create(['price' => 100, 'stock' => 10]);
    $product2 = Product::factory()->create(['price' => 50, 'stock' => 5]);

    $response = $this->actingAs($user)
        ->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 2],
                ['product_id' => $product2->id, 'quantity' => 1],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'order' => [
                'id',
                'order_number',
                'total_amount',
                'status',
                'customer',
                'items',
            ],
        ]);

    $this->assertDatabaseHas('orders', [
        'customer_id' => $customer->id,
        'total_amount' => 250.00,
    ]);

    expect($product1->fresh()->stock)->toBe(8);
    expect($product2->fresh()->stock)->toBe(4);
});

test('authenticated user can create order without customer', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

    $response = $this->actingAs($user)
        ->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('orders', [
        'customer_id' => null,
        'total_amount' => 100.00,
    ]);
});

test('authenticated user cannot create order with insufficient product stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock' => 2]);

    $response = $this->actingAs($user)
        ->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});
