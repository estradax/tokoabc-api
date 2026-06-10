<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductCustomerResource;
use App\Http\Resources\ProductResource;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search');

        $products = Product::query()
            ->with('media')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $media = $validated['media'] ?? null;
        unset($validated['media']);

        if (empty($validated['slug'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name']);
        }

        $product = Product::create($validated);

        if (!empty($media)) {
            foreach ($media as $item) {
                $product->media()->create([
                    'type' => $item['type'] ?? 'image',
                    'url' => $item['url'],
                    'sort_order' => $item['sort_order'] ?? 0,
                ]);
            }
        }

        $product->load('media');

        return response()->json([
            'message' => 'Product created successfully',
            'product' => new ProductResource($product),
        ], 201);
    }

    public function show(Product $product): ProductResource
    {
        $product->load('media');

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();
        $media = $validated['media'] ?? null;
        unset($validated['media']);

        if (empty($validated['slug'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $product->id);
        }

        $product->update($validated);

        if ($media !== null) {
            $product->media()->delete();
            foreach ($media as $item) {
                $product->media()->create([
                    'type' => $item['type'] ?? 'image',
                    'url' => $item['url'],
                    'sort_order' => $item['sort_order'] ?? 0,
                ]);
            }
        }

        $product->load('media');

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => new ProductResource($product),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    public function customers(Product $product): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->whereHas('orders.items', function ($query) use ($product) {
                $query->where('product_id', $product->id);
            })
            ->get()
            ->map(function ($customer) use ($product) {
                $items = OrderItem::query()
                    ->whereHas('order', function ($query) use ($customer) {
                        $query->where('customer_id', $customer->id);
                    })
                    ->where('product_id', $product->id)
                    ->get();

                $customer->total_quantity = $items->sum('quantity');
                $customer->total_spend = $items->sum(fn($item) => $item->quantity * $item->unit_price);
                $customer->last_purchased_at = $items->max('created_at');

                return $customer;
            })
            ->sortByDesc('total_quantity')
            ->values();

        return ProductCustomerResource::collection($customers);
    }

    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 2;

        while (
            Product::query()
                ->where('slug', $slug)
                ->when($excludeId, function ($query, $excludeId) {
                    $query->where('id', '!=', $excludeId);
                })
                ->exists()
        ) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }
}
