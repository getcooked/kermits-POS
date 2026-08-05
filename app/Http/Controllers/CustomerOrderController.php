<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerOrderController extends Controller
{
    public function index(): View
    {
        return view('shop.index', [
            'products' => Product::query()->available()->where('stock', '>', 0)->orderBy('category_order')->orderBy('name')->get(),
            'gcashQrPath' => SystemSetting::get('gcash_qr_path'),
        ]);
    }

    public function store(OrderRequest $request, OrderService $orders): RedirectResponse
    {
        $order = $orders->create(
            user: $request->user(),
            quantities: $request->selectedQuantities(),
            paymentStatus: 'pending',
            paymentMethod: $request->validated('payment_method'),
            paymentReference: $request->validated('payment_reference'),
            customer: $request->user(),
        );

        return redirect()->route('shop.orders.show', $order);
    }

    public function show(Order $order): View
    {
        abort_unless($order->user_id === request()->user()->id, 403);

        return view('shop.show', [
            'order' => $order->load('items.product'),
        ]);
    }
}
