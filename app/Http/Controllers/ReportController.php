<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:week,month,year,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'payment_method' => ['nullable', 'in:cash,gcash'],
        ]);

        [$period, $from, $to] = $this->resolvePeriod($filters);
        $days = $from->diffInDays($to) + 1;
        $previousFrom = $from->subDays($days);
        $previousTo = $from->subDay();

        $ordersQuery = Order::query()
            ->where('payment_status', 'paid')
            ->with(['user', 'customer'])
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);

        $allPaymentOrders = (clone $ordersQuery)->get();
        $ordersQuery->when(
            $filters['payment_method'] ?? null,
            fn ($query, $method) => $query->where('payment_method', $method),
        );

        $orders = $ordersQuery->latest()->get();
        $previousSales = Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$previousFrom->startOfDay(), $previousTo->endOfDay()])
            ->when(
                $filters['payment_method'] ?? null,
                fn ($query, $method) => $query->where('payment_method', $method),
            )
            ->sum('total');
        $salesTotal = (float) $orders->sum('total');
        $salesChange = $previousSales > 0
            ? (($salesTotal - (float) $previousSales) / (float) $previousSales) * 100
            : ($salesTotal > 0 ? 100 : 0);
        $chart = $this->salesChart($allPaymentOrders, $from, $to, $period);

        $topProducts = OrderItem::query()
            ->with('product')
            ->whereIn('order_id', $orders->pluck('id'))
            ->selectRaw('product_id, SUM(quantity) as units, SUM(subtotal) as sales')
            ->groupBy('product_id')
            ->orderByDesc('units')
            ->limit(5)
            ->get();

        $reservationsQuery = Reservation::query()
            ->with(['user', 'handler', 'items.product'])
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);
        $allReservations = (clone $reservationsQuery)->get();
        $reportReservations = (clone $reservationsQuery)->latest()->limit(10)->get();
        $approvedReservationIds = $allReservations
            ->whereIn('status', ['confirmed', 'completed'])
            ->pluck('id');
        $topRequestedProducts = ReservationItem::query()
            ->with('product')
            ->whereIn('reservation_id', $approvedReservationIds)
            ->selectRaw('product_id, SUM(quantity) as units, SUM(subtotal) as value')
            ->groupBy('product_id')
            ->orderByDesc('units')
            ->limit(5)
            ->get();

        $stockMovements = StockMovement::query()
            ->with(['product', 'user'])
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->latest()
            ->limit(12)
            ->get();
        $stockIn = StockMovement::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->where('type', 'stock_in')
            ->sum('quantity');
        $stockOut = StockMovement::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereIn('type', ['stock_out', 'sale', 'order_reserved'])
            ->sum('quantity');
        $products = Product::query()->orderBy('stock')->get();

        return view('reports.index', [
            'period' => $period,
            'periodFrom' => $from,
            'periodTo' => $to,
            'chart' => $chart,
            'orders' => $orders,
            'salesTotal' => $salesTotal,
            'salesChange' => $salesChange,
            'cashTotal' => $allPaymentOrders->where('payment_method', 'cash')->sum('total'),
            'cashCount' => $allPaymentOrders->where('payment_method', 'cash')->count(),
            'gcashTotal' => $allPaymentOrders->where('payment_method', 'gcash')->sum('total'),
            'gcashCount' => $allPaymentOrders->where('payment_method', 'gcash')->count(),
            'topProducts' => $topProducts,
            'products' => $products,
            'lowStock' => Product::query()->available()->lowStock()->count(),
            'inventoryValue' => $products->sum(fn (Product $product) => (float) $product->price * $product->stock),
            'stockIn' => $stockIn,
            'stockOut' => $stockOut,
            'reservations' => $reportReservations,
            'reservationCount' => $allReservations->count(),
            'pendingReservations' => $allReservations->where('status', 'pending')->count(),
            'approvedReservations' => $allReservations->whereIn('status', ['confirmed', 'completed'])->count(),
            'approvedReservationValue' => $allReservations->whereIn('status', ['confirmed', 'completed'])->sum('total_amount'),
            'topRequestedProducts' => $topRequestedProducts,
            'stockMovements' => $stockMovements,
        ]);
    }

    /**
     * @return array{0:string,1:CarbonImmutable,2:CarbonImmutable}
     */
    private function resolvePeriod(array $filters): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        if (($filters['from'] ?? null) && ($filters['to'] ?? null)) {
            return [
                'custom',
                CarbonImmutable::parse($filters['from'], config('app.timezone')),
                CarbonImmutable::parse($filters['to'], config('app.timezone')),
            ];
        }

        return match ($filters['period'] ?? 'month') {
            'week' => ['week', $today->startOfWeek(), $today->endOfWeek()],
            'year' => ['year', $today->startOfYear(), $today->endOfYear()],
            default => ['month', $today->startOfMonth(), $today->endOfMonth()],
        };
    }

    /**
     * @return array{labels:array<int,string>,cash:array<int,float>,gcash:array<int,float>,total:array<int,float>}
     */
    private function salesChart(Collection $orders, CarbonImmutable $from, CarbonImmutable $to, string $period): array
    {
        $buckets = collect();

        if ($period === 'year') {
            for ($date = $from->startOfMonth(); $date <= $to; $date = $date->addMonth()) {
                $buckets->push([
                    'key' => $date->format('Y-m'),
                    'label' => $date->format('M'),
                    'format' => 'Y-m',
                ]);
            }
        } else {
            for ($date = $from; $date <= $to; $date = $date->addDay()) {
                $buckets->push([
                    'key' => $date->format('Y-m-d'),
                    'label' => $period === 'week' ? $date->format('D') : $date->format('j'),
                    'format' => 'Y-m-d',
                ]);
            }
        }

        $values = $buckets->map(function (array $bucket) use ($orders): array {
            $matches = $orders->filter(
                fn (Order $order): bool => $order->created_at->format($bucket['format']) === $bucket['key']
            );
            $cash = (float) $matches->where('payment_method', 'cash')->sum('total');
            $gcash = (float) $matches->where('payment_method', 'gcash')->sum('total');

            return [
                'label' => $bucket['label'],
                'cash' => $cash,
                'gcash' => $gcash,
                'total' => $cash + $gcash,
            ];
        });

        return [
            'labels' => $values->pluck('label')->all(),
            'cash' => $values->pluck('cash')->all(),
            'gcash' => $values->pluck('gcash')->all(),
            'total' => $values->pluck('total')->all(),
        ];
    }

    public function receipt(Order $order): View
    {
        abort_unless($order->payment_status === 'paid', 404);

        return view('receipts.show', [
            'order' => $order->load(['user', 'customer', 'items.product']),
        ]);
    }
}
