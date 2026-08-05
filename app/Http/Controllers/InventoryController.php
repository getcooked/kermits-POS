<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryAdjustmentRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        return view('inventory.index', [
            'products' => Product::query()->orderBy('stock')->get(),
            'movements' => StockMovement::query()->with(['product', 'user'])->latest()->limit(50)->get(),
            'lowStock' => Product::query()->available()->lowStock()->count(),
            'totalUnits' => Product::query()->sum('stock'),
        ]);
    }

    public function update(
        InventoryAdjustmentRequest $request,
        Product $product,
        InventoryService $inventory,
    ): RedirectResponse {
        $inventory->adjust(
            product: $product,
            user: $request->user(),
            type: $request->string('type')->toString(),
            quantity: $request->integer('quantity'),
            note: $request->string('note')->trim()->toString() ?: null,
        );

        return back()->with('status', 'Inventory updated successfully.');
    }
}
