<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashierCheckoutRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Models\SystemSetting;
use App\Services\OrderService;
use App\Services\ReservationPushNotifier;
use App\Services\ReservationSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CashierController extends Controller
{
    public function index(): View
    {
        return view('roles.cashier', [
            'products' => Product::query()->available()->menuOrder()->get(),
            'gcashQrPath' => SystemSetting::get('gcash_qr_path'),
        ]);
    }

    public function customerOrders(): View
    {
        return view('roles.cashier-orders', [
            'orders' => Order::query()
                ->where('payment_status', 'pending')
                ->whereNotNull('customer_id')
                ->with(['customer', 'items.product', 'reservation'])
                ->latest()
                ->get(),
        ]);
    }

    public function reviewCustomerOrder(Order $order): View
    {
        $this->ensurePendingCustomerOrder($order);

        $order->load(['customer', 'items.product', 'reservation']);

        return view('roles.cashier-order-review', [
            'order' => $order,
            'availableProducts' => Product::query()
                ->available()
                ->where('stock', '>', 0)
                ->whereNotIn('id', $order->items->pluck('product_id'))
                ->menuOrder()
                ->get(),
        ]);
    }

    public function updateCustomerOrder(
        Request $request,
        Order $order,
        ReservationPushNotifier $reservationPushes,
    ): RedirectResponse {
        $validated = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'integer', 'min:0', 'max:22'],
            'new_quantities' => ['nullable', 'array'],
            'new_quantities.*' => ['nullable', 'integer', 'min:0', 'max:22'],
        ]);

        $itemsChanged = false;
        $itemsAdded = 0;
        DB::transaction(function () use ($order, $validated, $request, &$itemsChanged, &$itemsAdded): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensurePendingCustomerOrder($lockedOrder);
            $items = $lockedOrder->items()->lockForUpdate()->get();
            $requested = collect($validated['quantities'])->map(fn ($quantity) => (int) $quantity);
            $newRequested = collect($validated['new_quantities'] ?? [])
                ->map(fn ($quantity) => (int) $quantity)
                ->filter(fn (int $quantity): bool => $quantity > 0);

            if ($request->string('intent')->toString() === 'add_items' && $newRequested->isEmpty()) {
                throw ValidationException::withMessages(['new_quantities' => 'Choose at least one menu item to add.']);
            }

            if ($items->sum(fn ($item) => $requested->get((string) $item->id, 0)) + $newRequested->sum() < 1) {
                throw ValidationException::withMessages(['quantities' => 'Keep at least one item in the order.']);
            }

            $productIds = $items->pluck('product_id')
                ->merge($newRequested->keys()->map(fn ($id) => (int) $id))
                ->unique()
                ->sort()
                ->values();
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalCents = 0;
            foreach ($items as $item) {
                $newQuantity = $requested->get((string) $item->id, 0);
                $difference = $newQuantity - $item->quantity;
                $product = $products->get($item->product_id);
                if (! $product) {
                    throw ValidationException::withMessages(['quantities' => 'One of the original menu items is no longer available.']);
                }
                $stockBefore = $product->stock;

                if ($difference > 0 && $product->stock < $difference) {
                    throw ValidationException::withMessages([
                        'quantities.'.$item->id => $product->name.' does not have enough available stock.',
                    ]);
                }

                if ($difference !== 0) {
                    $itemsChanged = true;
                    $product->update(['stock' => $product->stock - $difference]);
                    StockMovement::query()->create([
                        'product_id' => $product->id,
                        'user_id' => $request->user()->id,
                        'type' => 'order_adjustment',
                        'quantity' => -$difference,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockBefore - $difference,
                        'note' => 'Cashier reviewed customer order #'.$lockedOrder->id,
                    ]);
                }

                if ($newQuantity === 0) {
                    $item->delete();

                    continue;
                }

                $subtotalCents = (int) round((float) $item->unit_price * 100) * $newQuantity;
                $item->update(['quantity' => $newQuantity, 'subtotal' => $subtotalCents / 100]);
                $totalCents += $subtotalCents;
            }

            $itemsByProduct = $items->keyBy('product_id');
            foreach ($newRequested as $productId => $quantity) {
                $productId = (int) $productId;
                $product = $products->get($productId);
                if (! $product || ! $product->active) {
                    throw ValidationException::withMessages(['new_quantities' => 'One or more selected menu items are no longer available.']);
                }
                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'new_quantities.'.$productId => $product->name.' does not have enough available stock.',
                    ]);
                }

                $existingItem = $itemsByProduct->get($productId);
                if ($existingItem?->exists) {
                    $combinedQuantity = (int) $existingItem->quantity + $quantity;
                    if ($combinedQuantity > 22) {
                        throw ValidationException::withMessages([
                            'new_quantities.'.$productId => $product->name.' cannot exceed 22 items in one order.',
                        ]);
                    }
                    $unitPriceCents = (int) round((float) $existingItem->unit_price * 100);
                    $existingItem->update([
                        'quantity' => $combinedQuantity,
                        'subtotal' => ($unitPriceCents * $combinedQuantity) / 100,
                    ]);
                } else {
                    $unitPriceCents = (int) round((float) $product->price * 100);
                    $lockedOrder->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPriceCents / 100,
                        'subtotal' => ($unitPriceCents * $quantity) / 100,
                    ]);
                }

                $stockBefore = $product->stock;
                $product->update(['stock' => $stockBefore - $quantity]);
                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'type' => 'order_adjustment',
                    'quantity' => -$quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockBefore - $quantity,
                    'note' => 'Cashier added items to customer order #'.$lockedOrder->id,
                ]);

                $totalCents += $unitPriceCents * $quantity;
                $itemsAdded += $quantity;
                $itemsChanged = true;
            }

            $lockedOrder->update(['total' => $totalCents / 100]);
        }, attempts: 3);

        if ($itemsChanged) {
            $reservation = Reservation::query()->where('order_id', $order->id)->first();
            if ($reservation) {
                $reservationPushes->notify($reservation, ['items']);
            }
        }

        $message = $itemsAdded > 0
            ? $itemsAdded.' '.($itemsAdded === 1 ? 'item was' : 'items were').' added to the order.'
            : 'Order review saved.';

        return redirect()->route('cashier.orders.review', $order)->with('status', $message);
    }

    public function checkout(CashierCheckoutRequest $request, OrderService $orders): RedirectResponse
    {
        $order = $orders->create(
            user: $request->user(),
            quantities: $request->selectedQuantities(),
            paymentStatus: 'paid',
            cashReceivedCents: $request->cashReceivedCents(),
            paymentMethod: $request->validated('payment_method'),
            paymentReference: $request->validated('payment_reference'),
        );

        return redirect()
            ->route('receipts.show', $order)
            ->with('status', 'Payment completed successfully.');
    }

    public function confirmCustomerPayment(Request $request, Order $order): RedirectResponse
    {
        DB::transaction(function () use ($order, $request): void {
            app(ReservationSchedule::class)->lock();
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensurePendingCustomerOrder($lockedOrder);
            $lockedOrder->load('reservation');
            if ($lockedOrder->reservation && ! in_array($lockedOrder->reservation->booking_status, ['pending', 'confirmed', 'completed'])) {
                throw ValidationException::withMessages(['reservation' => 'This reservation is no longer active. Review its schedule before collecting payment.']);
            }
            $amountDue = $lockedOrder->totalDue();

            if ($lockedOrder->payment_method === 'gcash') {
                if (! preg_match('/^\d{13}$/', (string) $lockedOrder->payment_reference)) {
                    throw ValidationException::withMessages(['payment_reference' => 'A valid 13-digit GCash reference is required.']);
                }
                $lockedOrder->update(['payment_status' => 'paid', 'cash_received' => null, 'change_due' => null]);
                $this->markReservationPaid($lockedOrder->reservation);

                return;
            }

            $validated = $request->validate([
                'cash_received' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            ]);
            $cash = round((float) $validated['cash_received'], 2);
            if ($cash < $amountDue) {
                throw ValidationException::withMessages(['cash_received' => 'Customer cash must cover the complete order total.']);
            }

            $lockedOrder->update([
                'payment_status' => 'paid',
                'cash_received' => $cash,
                'change_due' => $cash - $amountDue,
            ]);
            $this->markReservationPaid($lockedOrder->reservation);
        }, attempts: 3);

        return redirect()
            ->route('receipts.show', $order)
            ->with('status', 'Payment confirmed. The official receipt is ready to print.');
    }

    public function rejectCustomerOrder(Request $request, Order $order): RedirectResponse
    {
        DB::transaction(function () use ($request, $order): void {
            app(ReservationSchedule::class)->lock();
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensurePendingCustomerOrder($lockedOrder);
            $reservation = Reservation::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();
            if ($reservation?->booking_status === 'completed') {
                throw ValidationException::withMessages(['order' => 'An order for a completed reservation cannot be rejected.']);
            }
            $items = $lockedOrder->items()->lockForUpdate()->get();
            $products = Product::query()
                ->whereIn('id', $items->pluck('product_id')->unique()->sort()->values())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                if (! $product) {
                    throw ValidationException::withMessages(['order' => 'This order contains a product that can no longer be returned to stock.']);
                }

                $stockBefore = $product->stock;
                $stockAfter = $stockBefore + $item->quantity;
                $product->update(['stock' => $stockAfter]);
                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'type' => 'order_rejected',
                    'quantity' => $item->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'note' => 'Stock returned from rejected customer order #'.$lockedOrder->id,
                ]);
            }

            $lockedOrder->update([
                'payment_status' => 'rejected',
                'cash_received' => null,
                'change_due' => null,
            ]);

            if ($reservation) {
                $previousStatus = $reservation->booking_status;
                $nextStatus = match ($previousStatus) {
                    'confirmed' => 'cancelled',
                    'completed', 'cancelled', 'rejected' => $reservation->status,
                    default => 'rejected',
                };
                $updates = [
                    'payment_status' => 'rejected',
                    'handled_by' => $request->user()->id,
                ];
                if ($nextStatus !== $reservation->status) {
                    $updates['status'] = $nextStatus;
                }
                if (Schema::hasColumn('reservations', 'hold_expires_at')) {
                    $updates['hold_expires_at'] = null;
                }
                $reservation->update($updates);

                if ($nextStatus !== $previousStatus) {
                    $reservation->statusHistories()->create([
                        'from_status' => $previousStatus,
                        'to_status' => $nextStatus,
                        'changed_by' => $request->user()->id,
                    ]);
                }
            }
        }, attempts: 3);

        return redirect()
            ->route('cashier.orders.index')
            ->with('status', 'Order rejected. Reserved stock has been restored.');
    }

    private function markReservationPaid(?Reservation $reservation): void
    {
        if (! $reservation) {
            return;
        }
        $updates = ['payment_status' => 'paid'];
        if (Schema::hasColumn('reservations', 'hold_expires_at')) {
            $updates['hold_expires_at'] = null;
        }
        $reservation->update($updates);
    }

    private function ensurePendingCustomerOrder(Order $order): void
    {
        if ($order->customer_id === null) {
            throw ValidationException::withMessages(['order' => 'Only customer online orders can be reviewed here.']);
        }
        if ($order->payment_status !== 'pending') {
            throw ValidationException::withMessages(['order' => 'This order is no longer waiting for payment confirmation.']);
        }
    }
}
