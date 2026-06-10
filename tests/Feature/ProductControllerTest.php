<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('unauthenticated user cannot access product routes', function () {
    $this->getJson('/api/products')->assertUnauthorized();
    $this->postJson('/api/products', [])->assertUnauthorized();
});

test('authenticated user can list products with pagination', function () {
    $user = User::factory()->create();
    Product::factory()->count(20)->create();

    $response = $this->actingAs($user)
        ->getJson('/api/products');

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

test('authenticated user can search products', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Awesome Wireless Mouse']);
    Product::factory()->create(['name' => 'Mechanical Keyboard']);

    $response = $this->actingAs($user)
        ->getJson('/api/products?search=Mouse');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Awesome Wireless Mouse');
});

test('authenticated user can create a product with auto-generated slug', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/products', [
            'name' => 'Premium Coffee Beans',
            'sku' => 'CF-PRM-001',
            'description' => 'Organic roasted coffee beans.',
            'price' => 12.50,
            'stock' => 100,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'product' => [
                'id',
                'name',
                'slug',
                'sku',
                'description',
                'price',
                'stock',
            ],
        ]);

    $this->assertDatabaseHas('products', [
        'name' => 'Premium Coffee Beans',
        'slug' => 'premium-coffee-beans',
        'sku' => 'CF-PRM-001',
    ]);
});

test('authenticated user can update a product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Old Product Name',
        'price' => 10.00,
    ]);

    $response = $this->actingAs($user)
        ->putJson("/api/products/{$product->id}", [
            'name' => 'New Product Name',
            'price' => 15.00,
            'stock' => 50,
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'New Product Name',
        'price' => 15.00,
    ]);
});

test('authenticated user can delete a product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($user)
        ->deleteJson("/api/products/{$product->id}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('products', [
        'id' => $product->id,
    ]);
});

test('authenticated user can create a product with media', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/products', [
            'name' => 'Premium Coffee Beans',
            'sku' => 'CF-PRM-001',
            'description' => 'Organic roasted coffee beans.',
            'price' => 12.50,
            'stock' => 100,
            'media' => [
                [
                    'url' => 'http://localhost/storage/assets/image1.jpg',
                    'type' => 'image',
                    'sort_order' => 1,
                ],
                [
                    'url' => 'http://localhost/storage/assets/image2.jpg',
                    'type' => 'image',
                    'sort_order' => 2,
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'product' => [
                'id',
                'name',
                'slug',
                'sku',
                'description',
                'price',
                'stock',
                'media' => [
                    '*' => [
                        'id',
                        'product_id',
                        'type',
                        'url',
                        'sort_order',
                    ],
                ],
            ],
        ]);

    expect($response->json('product.media'))->toHaveCount(2);

    $this->assertDatabaseHas('product_media', [
        'product_id' => $response->json('product.id'),
        'url' => 'http://localhost/storage/assets/image1.jpg',
    ]);
});

test('authenticated user can update a product with media', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $product->media()->create([
        'url' => 'http://localhost/storage/assets/old.jpg',
        'type' => 'image',
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($user)
        ->putJson("/api/products/{$product->id}", [
            'name' => 'Updated Product Name',
            'price' => 15.00,
            'stock' => 50,
            'media' => [
                [
                    'url' => 'http://localhost/storage/assets/new1.jpg',
                    'type' => 'image',
                    'sort_order' => 1,
                ],
            ],
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseMissing('product_media', [
        'url' => 'http://localhost/storage/assets/old.jpg',
    ]);

    $this->assertDatabaseHas('product_media', [
        'product_id' => $product->id,
        'url' => 'http://localhost/storage/assets/new1.jpg',
    ]);
});

test('authenticated user can get a single product details', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $product->media()->create([
        'url' => 'http://localhost/storage/assets/test.jpg',
        'type' => 'image',
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/api/products/{$product->id}");

    $response->assertSuccessful()
        ->assertJsonPath('id', $product->id)
        ->assertJsonPath('name', $product->name)
        ->assertJsonStructure([
            'id',
            'name',
            'slug',
            'sku',
            'description',
            'price',
            'stock',
            'media',
            'created_at',
            'updated_at',
        ]);
});

test('authenticated user can list customers who purchased a product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 10.00]);

    // Create customers
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    // Customer 1 orders: 2 items of product (qty 3 and qty 2 = total 5)
    $order1a = Order::factory()->create(['customer_id' => $customer1->id]);
    OrderItem::factory()->create([
        'order_id' => $order1a->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_price' => 10.00,
    ]);

    $order1b = Order::factory()->create(['customer_id' => $customer1->id]);
    OrderItem::factory()->create([
        'order_id' => $order1b->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 10.00,
    ]);

    // Customer 2 orders: 1 item of product (qty 1)
    $order2 = Order::factory()->create(['customer_id' => $customer2->id]);
    OrderItem::factory()->create([
        'order_id' => $order2->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 10.00,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/api/products/{$product->id}/customers");

    $response->assertSuccessful();

    $data = $response->json();
    expect($data)->toHaveCount(2);

    // Order should be by total_quantity descending, so Customer 1 is first
    expect($data[0]['id'])->toBe($customer1->id);
    expect($data[0]['total_quantity'])->toBe(5);
    expect((float) $data[0]['total_spend'])->toBe(50.00);

    expect($data[1]['id'])->toBe($customer2->id);
    expect($data[1]['total_quantity'])->toBe(1);
    expect((float) $data[1]['total_spend'])->toBe(10.00);
});
