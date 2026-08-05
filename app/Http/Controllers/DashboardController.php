<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $paidOrders = Order::query()
            ->where('payment_status', 'paid')
            ->with('user')
            ->latest()
            ->get();

        $salesByDate = $paidOrders->groupBy(fn (Order $order) => $order->created_at->toDateString());
        $salesActivity = collect(range(11, 0))->map(function (int $daysAgo) use ($salesByDate): array {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'label' => $date->format('M d'),
                'amount' => (float) $salesByDate->get($date->toDateString(), collect())->sum('total'),
            ];
        });
        $activityMaximum = max(1, (float) $salesActivity->max('amount'));
        $salesActivity = $salesActivity->map(fn (array $day): array => [
            ...$day,
            'height' => $day['amount'] > 0 ? max(5, ($day['amount'] / $activityMaximum) * 100) : 2,
        ]);

        return view('dashboard', [
            'sales' => $paidOrders->sum('total'),
            'ordersCount' => $paidOrders->count(),
            'averageSale' => $paidOrders->avg('total') ?? 0,
            'itemsSold' => OrderItem::query()->whereHas('order', fn ($query) => $query->where('payment_status', 'paid'))->sum('quantity'),
            'productsCount' => Product::query()->available()->count(),
            'lowStock' => Product::query()->available()->lowStock()->orderBy('stock')->limit(5)->get(),
            'recentOrders' => $paidOrders->take(5),
            'salesActivity' => $salesActivity,
        ]);
    }
}
