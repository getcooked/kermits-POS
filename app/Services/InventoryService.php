<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function adjust(Product $product, User $user, string $type, int $quantity, ?string $note): void
    {
        DB::transaction(function () use ($product, $user, $type, $quantity, $note): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $stockBefore = $lockedProduct->stock;

            if ($type === 'stock_out' && $quantity > $stockBefore) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stock-out quantity exceeds available stock.',
                ]);
            }

            $stockAfter = match ($type) {
                'stock_in' => $stockBefore + $quantity,
                'stock_out' => $stockBefore - $quantity,
                'adjustment' => $quantity,
            };

            $lockedProduct->update(['stock' => $stockAfter]);

            StockMovement::query()->create([
                'product_id' => $lockedProduct->id,
                'user_id' => $user->id,
                'type' => $type,
                'quantity' => $type === 'adjustment' ? $stockAfter - $stockBefore : $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'note' => $note,
            ]);
        }, attempts: 3);
    }
}
