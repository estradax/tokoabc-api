<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'summary' => [
                'total_revenue' => (float) ($this['summary']['total_revenue'] ?? 0),
                'total_orders' => (int) ($this['summary']['total_orders'] ?? 0),
                'average_order_value' => (float) ($this['summary']['average_order_value'] ?? 0),
                'total_items_sold' => (int) ($this['summary']['total_items_sold'] ?? 0),
            ],
            'chart_data' => $this['chart_data'] ?? [],
            'top_products' => collect($this['top_products'] ?? [])->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity_sold' => (int) ($product->quantity_sold ?? 0),
                    'total_revenue' => (float) ($product->total_revenue ?? 0),
                ];
            })->all(),
        ];
    }
}
