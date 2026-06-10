<?php

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
