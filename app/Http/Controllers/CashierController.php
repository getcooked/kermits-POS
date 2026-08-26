<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashierCheckoutRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\SystemSetting;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return view('roles.cashier-order-review', [
            'order' => $order->load(['customer', 'items.product', 'reservation']),
        ]);
    }

    public function updateCustomerOrder(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'integer', 'min:0', 'max:22'],
        ]);

        DB::transaction(function () use ($order, $validated, $request): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensurePendingCustomerOrder($lockedOrder);
            $items = $lockedOrder->items()->lockForUpdate()->get();
            $requested = collect($validated['quantities'])->map(fn ($quantity) => (int) $quantity);

            if ($items->sum(fn ($item) => $requested->get((string) $item->id, 0)) < 1) {
                throw ValidationException::withMessages(['quantities' => 'Keep at least one item in the order.']);
            }

            $totalCents = 0;
            foreach ($items as $item) {
                $newQuantity = $requested->get((string) $item->id, 0);
                $difference = $newQuantity - $item->quantity;
                $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
                $stockBefore = $product->stock;

                if ($difference > 0 && $product->stock < $difference) {
                    throw ValidationException::withMessages([
                        'quantities.'.$item->id => $product->name.' does not have enough available stock.',
                    ]);
                }

                if ($difference !== 0) {
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

            $lockedOrder->update(['total' => $totalCents / 100]);
        }, attempts: 3);

        return redirect()->route('cashier.orders.review', $order)->with('status', 'Order review saved.');
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
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensurePendingCustomerOrder($lockedOrder);
            $lockedOrder->load('reservation');
            $amountDue = $lockedOrder->totalDue();

            if ($lockedOrder->payment_method === 'gcash') {
                if (! preg_match('/^\d{13}$/', (string) $lockedOrder->payment_reference)) {
                    throw ValidationException::withMessages(['payment_reference' => 'A valid 13-digit GCash reference is required.']);
                }
                $lockedOrder->update(['payment_status' => 'paid', 'cash_received' => null, 'change_due' => null]);
                $lockedOrder->reservation?->update(['payment_status' => 'paid']);

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
            $lockedOrder->reservation?->update(['payment_status' => 'paid']);
        }, attempts: 3);

        return redirect()->route('cashier.orders.index')->with('status', 'Complete order payment confirmed. Admin sales dashboards are now updated.');
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
