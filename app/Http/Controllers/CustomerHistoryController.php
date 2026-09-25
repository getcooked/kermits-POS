<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user();
        $orders = $customer->purchases()
            ->with(['items.product', 'reservation'])
            ->latest()
            ->get();
        $todayOrders = $orders->filter(fn ($order): bool => $order->created_at->isToday());

        return view('customer.history', [
            'reservations' => $customer->reservations()
                ->with(['items.product', 'statusHistories.changedBy'])
                ->latest('reservation_at')
                ->get(),
            'orders' => $orders,
            'todayOrderCount' => $todayOrders->count(),
            'todayPaidTotal' => $todayOrders
                ->where('payment_status', 'paid')
                ->sum(fn ($order): float => $order->totalDue()),
        ]);
    }
}
