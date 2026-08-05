<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function create(
        User $user,
        array $quantities,
        string $paymentStatus,
        ?int $cashReceivedCents = null,
        string $paymentMethod = 'cash',
        ?string $paymentReference = null,
        ?User $customer = null,
    ): Order {
        if ($quantities === []) {
            throw ValidationException::withMessages([
                'quantities' => 'Select at least one product.',
            ]);
        }

        return DB::transaction(function () use ($user, $quantities, $paymentStatus, $cashReceivedCents, $paymentMethod, $paymentReference, $customer): Order {
            $products = Product::query()
                ->whereIn('id', array_keys($quantities))
                ->where('active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalCents = 0;
            $items = [];
            $stockMovements = [];

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get((int) $productId);

                if (! $product || $product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'quantities' => 'One or more products are unavailable or have insufficient stock.',
                    ]);
                }

                $unitPriceCents = (int) round((float) $product->price * 100);
                $subtotalCents = $unitPriceCents * $quantity;
                $totalCents += $subtotalCents;

                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPriceCents / 100,
                    'subtotal' => $subtotalCents / 100,
                ];

                $stockBefore = $product->stock;
                $product->decrement('stock', $quantity);
                $stockMovements[] = [
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'type' => $paymentStatus === 'paid' ? 'sale' : 'order_reserved',
                    'quantity' => $quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockBefore - $quantity,
                ];
            }

            if ($paymentStatus === 'paid' && $paymentMethod === 'cash' && ($cashReceivedCents === null || $cashReceivedCents < $totalCents)) {
                throw ValidationException::withMessages([
                    'cash_received' => 'The customer cash is not enough to pay the order total.',
                ]);
            }

            $order = Order::query()->create([
                'user_id' => $user->id,
                'customer_id' => $customer?->id,
                'total' => $totalCents / 100,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'payment_reference' => $paymentReference,
                'cash_received' => $cashReceivedCents === null ? null : $cashReceivedCents / 100,
                'change_due' => $paymentMethod === 'cash' && $cashReceivedCents !== null ? ($cashReceivedCents - $totalCents) / 100 : null,
            ]);

            $order->items()->createMany($items);
            foreach ($stockMovements as $movement) {
                StockMovement::query()->create([
                    ...$movement,
                    'note' => ($paymentStatus === 'paid' ? 'Completed sale' : 'Customer order').' #'.$order->id,
                ]);
            }

            return $order;
        }, attempts: 3);
    }
}
