<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        return InventoryItem::query()->with('product')->orderBy('id', 'desc')->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer',
            'reorder_level' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
        ]);

        $item = InventoryItem::create($data);

        return response()->json($item->load('product'), 201);
    }

    public function show(InventoryItem $inventoryItem)
    {
        return $inventoryItem->load('product');
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $data = $request->validate([
            'quantity' => 'nullable|integer',
            'reorder_level' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
        ]);

        $inventoryItem->update($data);

        return $inventoryItem->load('product');
    }

    public function adjust(Request $request, InventoryItem $inventoryItem)
    {
        $data = $request->validate([
            'delta' => 'required|integer',
            'reason' => 'nullable|string|max:255',
        ]);

        $inventoryItem->quantity += $data['delta'];
        $inventoryItem->save();

        return $inventoryItem->load('product');
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();

        return response()->noContent();
    }
}
