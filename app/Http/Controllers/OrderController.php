<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $orders = Order::query()
            ->with('customer')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        $order->load(['customer', 'items.product']);

        return new OrderResource($order);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = DB::transaction(function () use ($validated) {
            $totalAmount = 0.00;
            $itemsData = [];

            foreach ($validated['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);

                if ($product->stock < $itemData['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["The product '{$product->name}' does not have enough stock."],
                    ]);
                }

                $product->decrement('stock', $itemData['quantity']);

                $unitPrice = $product->price;
                $subtotal = $unitPrice * $itemData['quantity'];
                $totalAmount += $subtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_json' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => (float) $product->price,
                    ],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $unitPrice,
                ];
            }

            do {
                $orderNumber = 'ORD-' . strtoupper(Str::random(8));
            } while (Order::query()->where('order_number', $orderNumber)->exists());

            $order = Order::query()->create([
                'customer_id' => $validated['customer_id'] ?? null,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            foreach ($itemsData as $item) {
                $order->items()->create($item);
            }

            return $order;
        });

        $order->load(['customer', 'items.product']);

        return response()->json([
            'message' => 'Order created successfully',
            'order' => new OrderResource($order),
        ], 201);
    }

    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        $order->update($request->validated());
        $order->load(['customer', 'items.product']);

        return new OrderResource($order);
    }
}
