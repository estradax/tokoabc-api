<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

test('unauthenticated user cannot upload assets', function () {
    $this->postJson('/api/upload', [])
        ->assertUnauthorized();
});

test('authenticated user can upload an asset and retrieve resource payload', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('product-image.jpg');

    $response = $this->actingAs($user)
        ->postJson('/api/upload', [
            'file' => $file,
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'path',
            'url',
        ]);

    $path = $response->json('path');
    Storage::disk('public')->assertExists($path);
});

test('asset upload fails if the file exceeds the 512MB limit', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    // 513MB in KB = 513 * 1024 = 525312 KB
    $file = UploadedFile::fake()->create('large-video.mp4', 525312);

    $response = $this->actingAs($user)
        ->postJson('/api/upload', [
            'file' => $file,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});
