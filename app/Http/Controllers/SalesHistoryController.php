<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        [$period, $from, $to] = $this->resolvePeriod($filters);
        $query = $this->historyQuery($request->user(), $filters, $from, $to);
        $summaryOrders = (clone $query)->with('reservation')->get();
        $salesTotal = $summaryOrders->sum(fn (Order $order): float => $order->totalDue());

        $orders = (clone $query)
            ->with(['customer', 'processor', 'reservation'])
            ->latest('paid_at')
            ->paginate(20)
            ->withQueryString();

        $cashiers = collect();
        $cashierComparison = collect();

        if ($request->user()->hasRole(User::ROLE_SUPER_ADMIN)) {
            $cashiers = User::withTrashed()
                ->where('role', User::ROLE_CASHIER)
                ->orderBy('name')
                ->get(['id', 'name', 'deleted_at']);

            $comparisonFilters = collect($filters)->except(['cashier_id', 'search'])->all();
            $cashierComparison = $this->historyQuery($request->user(), $comparisonFilters, $from, $to)
                ->with(['processor', 'reservation'])
                ->get()
                ->groupBy(fn (Order $order): string => (string) ($order->processed_by ?? 'system'))
                ->map(function ($cashierOrders): array {
                    $first = $cashierOrders->first();

                    return [
                        'name' => $first->processor?->name ?? 'Online / System',
                        'count' => $cashierOrders->count(),
                        'sales' => $cashierOrders->sum(fn (Order $order): float => $order->totalDue()),
                    ];
                })
                ->sortByDesc('sales')
                ->values();
        }

        return view('sales-history.index', [
            'period' => $period,
            'periodFrom' => $from,
            'periodTo' => $to,
            'orders' => $orders,
            'salesTotal' => $salesTotal,
            'transactionCount' => $summaryOrders->count(),
            'averageSale' => $summaryOrders->isEmpty() ? 0 : $salesTotal / $summaryOrders->count(),
            'cashTotal' => $summaryOrders->where('payment_method', 'cash')->sum(fn (Order $order): float => $order->totalDue()),
            'gcashTotal' => $summaryOrders->where('payment_method', 'gcash')->sum(fn (Order $order): float => $order->totalDue()),
            'paymongoTotal' => $summaryOrders->where('payment_method', 'paymongo')->sum(fn (Order $order): float => $order->totalDue()),
            'cashiers' => $cashiers,
            'cashierComparison' => $cashierComparison,
            'isSuperAdmin' => $request->user()->hasRole(User::ROLE_SUPER_ADMIN),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        [, $from, $to] = $this->resolvePeriod($filters);
        $query = $this->historyQuery($request->user(), $filters, $from, $to)
            ->with(['customer', 'processor', 'reservation'])
            ->oldest('paid_at');
        $filename = 'cashier-sales-'.$from->format('Y-m-d').'-to-'.$to->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Paid at', 'Receipt', 'Customer', 'Cashier', 'Payment method', 'Reference', 'Total paid']);

            foreach ($query->get() as $order) {
                fputcsv($output, [
                    $order->paid_at?->format('Y-m-d h:i A'),
                    '#'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
                    $this->safeCsvValue($order->customer?->name ?? 'Walk-in Customer'),
                    $this->safeCsvValue($order->processor?->name ?? 'Online / System'),
                    match ($order->payment_method) {
                        'gcash' => 'GCash',
                        'paymongo' => 'PayMongo',
                        default => 'Cash',
                    },
                    $this->safeCsvValue($order->payment_reference ?? ''),
                    number_format($order->totalDue(), 2, '.', ''),
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'period' => ['nullable', 'in:today,week,month,custom'],
            'from' => ['nullable', 'required_if:period,custom', 'date'],
            'to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:from'],
            'payment_method' => ['nullable', 'in:cash,gcash,paymongo'],
            'cashier_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', User::ROLE_CASHIER),
            ],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /**
     * @return array{0:string,1:CarbonImmutable,2:CarbonImmutable}
     */
    private function resolvePeriod(array $filters): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        if (($filters['period'] ?? null) === 'custom') {
            return [
                'custom',
                CarbonImmutable::parse($filters['from'], config('app.timezone'))->startOfDay(),
                CarbonImmutable::parse($filters['to'], config('app.timezone'))->endOfDay(),
            ];
        }

        return match ($filters['period'] ?? 'today') {
            'week' => ['week', $today->startOfWeek(), $today->endOfWeek()],
            'month' => ['month', $today->startOfMonth(), $today->endOfMonth()],
            default => ['today', $today, $today->endOfDay()],
        };
    }

    private function historyQuery(
        User $viewer,
        array $filters,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): Builder {
        $query = Order::query()
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to]);

        if ($viewer->hasRole(User::ROLE_CASHIER)) {
            $query->where('processed_by', $viewer->id);
        } elseif (isset($filters['cashier_id'])) {
            $query->where('processed_by', $filters['cashier_id']);
        }

        $query->when(
            $filters['payment_method'] ?? null,
            fn (Builder $builder, string $method): Builder => $builder->where('payment_method', $method),
        );

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $receipt = ltrim($search, '#0');
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $builder) use ($receipt, $like): void {
                if ($receipt !== '' && ctype_digit($receipt)) {
                    $builder->whereKey((int) $receipt);
                } else {
                    $builder->whereRaw('1 = 0');
                }

                $builder
                    ->orWhere('payment_reference', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like));
            });
        }

        return $query;
    }

    private function safeCsvValue(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
