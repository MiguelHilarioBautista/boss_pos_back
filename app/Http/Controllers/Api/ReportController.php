<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaleItem;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function summary(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();

        $salesQuery = Sale::query()->whereBetween('created_at', [$from, $to]);

        $summary = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'orders_count' => (clone $salesQuery)->count(),
            'total_sales' => (clone $salesQuery)->sum('total'),
            'total_tax' => (clone $salesQuery)->sum('tax_total'),
            'total_discount' => (clone $salesQuery)->sum('discount_total'),
            'total_paid' => (clone $salesQuery)->where('payment_status', 'paid')->sum('total'),
        ];

        $topProducts = SaleItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        return response()->json([
            'summary' => $summary,
            'top_products' => $topProducts,
        ]);
    }
}


