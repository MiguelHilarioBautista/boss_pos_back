<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index()
    {
        return Sale::query()->with(['customer', 'items.product', 'payments'])->orderByDesc('id')->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_total' => 'nullable|numeric|min:0',
            'payment' => 'nullable|array',
            'payment.provider' => 'nullable|string|max:255',
            'payment.method' => 'nullable|string|max:255',
            'payment.amount' => 'nullable|numeric|min:0',
            'payment.currency' => 'nullable|string|size:3',
            'payment.status' => 'nullable|string|max:50',
            'payment.external_id' => 'nullable|string|max:255',
            'payment.meta' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($data, $request) {
            $subtotal = 0;
            $taxTotal = 0;
            $discountTotal = 0;

            $sale = Sale::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'subtotal' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'total' => 0,
                'payment_status' => 'pending',
                'sale_status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $unitPrice = isset($itemData['unit_price']) ? (float) $itemData['unit_price'] : (float) $product->price;
                $quantity = (int) $itemData['quantity'];
                $discount = isset($itemData['discount_total']) ? (float) $itemData['discount_total'] : 0.0;
                $base = ($unitPrice * $quantity) - $discount;
                $tax = $base * ((float) $product->tax_rate / 100);
                $lineTotal = $base + $tax;

                $subtotal += $base;
                $taxTotal += $tax;
                $discountTotal += $discount;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => (float) $product->cost,
                    'tax_rate' => (float) $product->tax_rate,
                    'discount_total' => $discount,
                    'line_total' => $lineTotal,
                ]);

                $inventory = InventoryItem::firstOrCreate(['product_id' => $product->id], ['quantity' => 0]);
                $inventory->decrement('quantity', $quantity);
            }

            $sale->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'total' => $subtotal + $taxTotal,
            ]);

            if (!empty($data['payment'])) {
                $payment = Payment::create([
                    'sale_id' => $sale->id,
                    'provider' => $data['payment']['provider'] ?? null,
                    'method' => $data['payment']['method'] ?? null,
                    'amount' => $data['payment']['amount'] ?? $sale->total,
                    'currency' => $data['payment']['currency'] ?? null,
                    'status' => $data['payment']['status'] ?? 'paid',
                    'external_id' => $data['payment']['external_id'] ?? null,
                    'paid_at' => now(),
                    'meta' => $data['payment']['meta'] ?? null,
                ]);

                $sale->update([
                    'payment_status' => $payment->status,
                    'paid_at' => $payment->status === 'paid' ? now() : null,
                ]);
            }

            return $sale->load(['customer', 'items.product', 'payments']);
        });
    }

    public function show(Sale $sale)
    {
        return $sale->load(['customer', 'items.product', 'payments']);
    }

    public function update(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'payment_status' => 'nullable|string|max:50',
            'sale_status' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $sale->update($data);

        return $sale->load(['customer', 'items.product', 'payments']);
    }

    public function addPayment(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'provider' => 'nullable|string|max:255',
            'method' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'status' => 'nullable|string|max:50',
            'external_id' => 'nullable|string|max:255',
            'meta' => 'nullable|array',
        ]);

        $payment = Payment::create([
            'sale_id' => $sale->id,
            'provider' => $data['provider'] ?? null,
            'method' => $data['method'] ?? null,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? null,
            'status' => $data['status'] ?? 'paid',
            'external_id' => $data['external_id'] ?? null,
            'paid_at' => ($data['status'] ?? 'paid') === 'paid' ? now() : null,
            'meta' => $data['meta'] ?? null,
        ]);

        $totalPaid = $sale->payments()->where('status', 'paid')->sum('amount');
        $sale->update([
            'payment_status' => $totalPaid >= $sale->total ? 'paid' : 'partial',
            'paid_at' => $totalPaid >= $sale->total ? now() : null,
        ]);

        return $sale->load(['customer', 'items.product', 'payments']);
    }

    public function payments(Sale $sale)
    {
        return $sale->payments()->orderByDesc('id')->get();
    }
}






