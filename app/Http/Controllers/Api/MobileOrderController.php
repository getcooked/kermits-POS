<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()->with('items.product')->where('customer_id', $request->user()->id)
            ->latest()->get()->map(fn (Order $order): array => $this->data($order));

        return response()->json(['data' => $orders]);
    }

    public function store(Request $request, OrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'payment_method' => ['required', 'in:cash,gcash'],
            'payment_reference' => ['nullable', 'required_if:payment_method,gcash', 'digits:13'],
        ]);
        $quantities = collect($validated['items'])->mapWithKeys(fn (array $item): array => [(int) $item['product_id'] => (int) $item['quantity']])->all();
        $order = $orders->create(
            user: $request->user(), quantities: $quantities, paymentStatus: 'pending',
            paymentMethod: $validated['payment_method'], paymentReference: $validated['payment_reference'] ?? null,
            customer: $request->user(),
        )->load('items.product');

        return response()->json(['data' => $this->data($order)], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        return response()->json(['data' => $this->data($order->load('items.product'))]);
    }

    private function data(Order $order): array
    {
        return [
            'id' => $order->id, 'total' => (float) $order->total, 'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status, 'payment_reference' => $order->payment_reference,
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $order->items->map(fn ($item): array => [
                'product_id' => $item->product_id, 'name' => $item->product?->name ?? 'Product',
                'quantity' => $item->quantity, 'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ])->values(),
        ];
    }
}
