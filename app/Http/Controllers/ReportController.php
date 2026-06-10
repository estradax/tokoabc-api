<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\SalesReportRequest;
use App\Http\Resources\SalesReportResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function sales(SalesReportRequest $request): SalesReportResource
    {
        $validated = $request->validated();
        $period = $validated['period'];

        $startDate = null;
        $endDate = null;

        if ($period === 'weekly') {
            $startDate = Carbon::today()->subDays(6)->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        } elseif ($period === 'monthly') {
            $startDate = Carbon::today()->subMonths(11)->startOfMonth()->startOfDay();
            $endDate = Carbon::today()->endOfMonth()->endOfDay();
        } else {
            $startDate = Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay();
            $endDate = Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfDay();
        }

        $totalRevenue = (float) Order::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        $totalOrders = Order::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        $totalItemsSold = (int) DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->sum('order_items.quantity');

        $chartData = [];

        if ($period === 'weekly' || $period === 'custom') {
            $salesByDay = Order::query()
                ->where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total_amount) as revenue'),
                    DB::raw('COUNT(*) as orders')
                )
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                $dateStr = $current->format('Y-m-d');
                $dayLabel = $period === 'weekly'
                    ? $current->translatedFormat('D')
                    : $current->translatedFormat('d M');

                $dayData = $salesByDay->get($dateStr);

                $chartData[] = [
                    'label' => $dayLabel,
                    'date' => $dateStr,
                    'revenue' => $dayData ? (float) $dayData->revenue : 0.0,
                    'orders' => $dayData ? (int) $dayData->orders : 0,
                ];

                $current->addDay();
            }
        } else {
            $salesByMonth = Order::query()
                ->where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                    DB::raw('SUM(total_amount) as revenue'),
                    DB::raw('COUNT(*) as orders')
                )
                ->groupBy('month')
                ->get()
                ->keyBy('month');

            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                $monthStr = $current->format('Y-m');
                $monthLabel = $current->translatedFormat('M Y');

                $monthData = $salesByMonth->get($monthStr);

                $chartData[] = [
                    'label' => $monthLabel,
                    'date' => $monthStr,
                    'revenue' => $monthData ? (float) $monthData->revenue : 0.0,
                    'orders' => $monthData ? (int) $monthData->orders : 0,
                ];

                $current->addMonth();
            }
        }

        $topItems = OrderItem::whereHas('order', function ($query) use ($startDate, $endDate) {
            $query->where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate]);
        })
            ->select(
                'product_id',
                'product_json',
                DB::raw('SUM(quantity) as quantity_sold'),
                DB::raw('SUM(quantity * unit_price) as total_revenue')
            )
            ->groupBy('product_id', 'product_json')
            ->orderByDesc('quantity_sold')
            ->limit(5)
            ->get();

        $productIds = $topItems->pluck('product_id')->filter();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $topProducts = $topItems->map(function ($item) use ($products) {
            $product = $products->get($item->product_id);

            return (object) [
                'id' => $item->product_id,
                'name' => $product->name ?? $item->product_json['name'] ?? 'Unknown Product',
                'sku' => $product->sku ?? $item->product_json['sku'] ?? 'N/A',
                'quantity_sold' => (int) $item->quantity_sold,
                'total_revenue' => (float) $item->total_revenue,
            ];
        });

        return new SalesReportResource([
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'average_order_value' => $averageOrderValue,
                'total_items_sold' => $totalItemsSold,
            ],
            'chart_data' => $chartData,
            'top_products' => $topProducts,
        ]);
    }
}
