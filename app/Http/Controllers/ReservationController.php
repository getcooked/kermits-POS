<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationStatusRequest;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SystemSetting;
use App\Services\PayMongoCheckout;
use App\Services\ReservationPricing;
use App\Services\ReservationSchedule;
use App\Services\TableLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ReservationController extends Controller
{
    public function create(Request $request, ReservationPricing $pricing, TableLayout $tables): View
    {
        return view('reservations.create', [
            'products' => Product::query()->available()->where('stock', '>', 0)->menuOrder()->get(),
            'tableFees' => $pricing->tableFees(),
            'diningTables' => $tables->activeTables()->sortBy('number')->values(),
            'exclusiveFee' => $pricing->exclusiveFee(),
            'downpaymentPercent' => $pricing->downpaymentPercent(),
            'exclusiveMinDate' => now()->addDays((int) config('reservations.exclusive_min_days_ahead'))->toDateString(),
            'gcashQrPath' => SystemSetting::get('gcash_qr_path'),
            'paymongoEnabled' => PayMongoCheckout::enabled(),
        ]);
    }

    public function store(StoreReservationRequest $request, ReservationSchedule $schedules, ReservationPricing $pricing): RedirectResponse
    {
        $proofPath = $request->hasFile('payment_proof')
            ? $request->file('payment_proof')->store('payment-proofs', 'local')
            : null;

        try {
            $reservation = DB::transaction(function () use ($request, $proofPath, $schedules, $pricing): Reservation {
                $schedules->lock();
                $reservationFee = $request->validated('type') === 'table'
                    ? $pricing->tableFee((int) $request->validated('table_size'))
                    : $pricing->exclusiveFee();
                $reservation = $schedules->reserve([
                    ...$request->safe()->except(['menu_items', 'payment_proof', 'payment_plan']),
                    'user_id' => $request->user()->id,
                    'customer_name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'reservation_at' => $schedules->normalize($request->validated('reservation_at')),
                    'reference' => $this->newReference(),
                    'status' => 'pending',
                    'guests' => $request->validated('type') === 'table'
                        ? $request->validated('table_size')
                        : $request->validated('guests'),
                    'reservation_fee' => $reservationFee,
                    'food_total' => 0,
                    'total_amount' => $reservationFee,
                    'payment_method' => $request->validated('payment_method'),
                    'payment_status' => 'pending',
                    'payment_proof_path' => $proofPath,
                ]);

                $quantities = $request->selectedMenuItems();
                $foodTotal = 0.0;
                if ($quantities !== []) {
                    $products = Product::query()->available()->whereIn('id', array_keys($quantities))->get()->keyBy('id');
                    if ($products->count() !== count($quantities)) {
                        throw ValidationException::withMessages(['menu_items' => 'One or more selected menu items are unavailable.']);
                    }

                    $items = collect($quantities)->map(function (int $quantity, int|string $productId) use ($products): array {
                        $product = $products->get((int) $productId);
                        $price = (float) $product->price;

                        return ['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $price, 'subtotal' => $price * $quantity];
                    })->values();
                    $foodTotal = (float) $items->sum('subtotal');
                    $reservation->items()->createMany($items->all());
                }

                $total = $reservationFee + $foodTotal;
                $reservation->update([
                    'food_total' => $foodTotal,
                    'total_amount' => $total,
                    'downpayment_amount' => $request->validated('type') === 'exclusive' ? $pricing->downpayment($total, $request->paymentPlan()) : null,
                ]);

                $reservation->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => 'pending',
                    'changed_by' => $request->user()->id,
                ]);

                if ($request->validated('payment_method') === 'paymongo') {
                    $order = Order::query()->create([
                        'user_id' => $request->user()->id,
                        'customer_id' => $request->user()->id,
                        'total' => 0,
                        'payment_method' => 'paymongo',
                        'payment_status' => 'pending',
                    ]);
                    $reservation->update(['order_id' => $order->id]);
                }

                return $reservation;
            });
        } catch (Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
        }

        if ($request->validated('payment_method') === 'paymongo') {
            try {
                return redirect()->away(app(PayMongoCheckout::class)->urlFor($reservation->order()->firstOrFail()));
            } catch (Throwable $exception) {
                report($exception);

                $failureUrl = URL::temporarySignedRoute(
                    'reservations.success',
                    now()->addMinutes(30),
                    ['reference' => $reservation->reference],
                );

                return redirect()->to($failureUrl)
                    ->with('payment_error', 'Your reservation was saved, but PayMongo checkout is unavailable. Please try the payment link again.');
            }
        }

        $url = URL::temporarySignedRoute(
            'reservations.success',
            now()->addMinutes(30),
            ['reference' => $reservation->reference],
        );

        return redirect()->to($url);
    }

    public function success(Request $request, string $reference): View
    {
        $reservation = Reservation::query()
            ->with('items.product')
            ->where('reference', $reference)
            ->whereBelongsTo($request->user())
            ->firstOrFail();

        return view('reservations.success', [
            'reservation' => $reservation,
        ]);
    }

    public function show(Request $request, Reservation $reservation): View
    {
        $this->authorizeViewer($request, $reservation);

        return view('reservations.show', [
            'reservation' => $reservation->load(['user', 'handler', 'items.product', 'statusHistories.changedBy']),
        ]);
    }

    public function receipt(Request $request, Reservation $reservation): View
    {
        $this->authorizeViewer($request, $reservation);

        return view('reservations.receipt', [
            'reservation' => $reservation->load(['user', 'items.product']),
        ]);
    }

    private function authorizeViewer(Request $request, Reservation $reservation): void
    {
        $canView = $request->user()->hasRole('super_admin', 'admin')
            || $reservation->user_id === $request->user()->id;

        abort_unless($canView, 403);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,completed,cancelled,rejected,expired'],
            'type' => ['nullable', 'in:table,exclusive'],
            'when' => ['nullable', 'in:upcoming,past,all'],
            'view' => ['nullable', 'in:list,timeline'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($filters['search'] ?? '');
        $view = $filters['view'] ?? 'list';
        $when = $filters['when'] ?? 'upcoming';
        $today = today();

        $stats = [
            'pending' => Reservation::query()->withBookingStatus('pending')->count(),
            'today' => Reservation::query()->whereDate('reservation_at', $today)
                ->where(fn ($query) => $query->withBookingStatus('pending')->orWhere('status', 'confirmed'))->count(),
            'confirmed' => Reservation::query()->where('status', 'confirmed')->where('reservation_at', '>=', $today)->count(),
            'completed' => Reservation::query()->where('status', 'completed')->count(),
        ];

        if ($view === 'timeline') {
            $date = isset($filters['date']) ? Carbon::parse($filters['date'])->startOfDay() : $today;

            return view('reservations.index', compact('view', 'when', 'stats', 'date') + [
                'timeline' => $this->timeline($date),
            ]);
        }

        $scoped = fn () => Reservation::query()
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($when === 'upcoming', fn ($query) => $query->where('reservation_at', '>=', $today))
            ->when($when === 'past', fn ($query) => $query->where('reservation_at', '<', $today))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where('customer_name', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like);
            }));

        $statusCounts = collect(['pending', 'confirmed', 'completed', 'cancelled', 'rejected', 'expired'])
            ->mapWithKeys(fn (string $status) => [$status => $scoped()->withBookingStatus($status)->count()]);

        $reservations = $scoped()
            ->with(['handler', 'items.product', 'diningTable'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->withBookingStatus($status))
            ->orderBy('reservation_at', $when === 'upcoming' ? 'asc' : 'desc')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('reservations.index', compact('view', 'when', 'stats', 'statusCounts', 'reservations', 'search') + [
            'totalCount' => $scoped()->count(),
        ]);
    }

    /**
     * One row per table for the day, each booking placed as a block between its arrival and
     * estimated end. Bookings that overlap on the same row get their own lane and are flagged.
     */
    private function timeline(Carbon $date): array
    {
        $open = $date->copy()->setTimeFromTimeString(config('reservations.opening_time'));
        $close = $date->copy()->setTimeFromTimeString(config('reservations.closing_time'));
        $span = max(1, (int) $open->diffInMinutes($close));

        $reservations = Reservation::query()
            ->with('diningTable')
            ->whereDate('reservation_at', $date)
            ->where(fn ($query) => $query->withBookingStatus('pending')->orWhereIn('status', ['confirmed', 'completed']))
            ->orderBy('reservation_at')
            ->get();

        $rows = DiningTable::query()->active()->orderBy('number')->get()
            ->mapWithKeys(fn (DiningTable $table) => [$table->id => ['label' => $table->label(), 'seats' => $table->seats, 'items' => []]])
            ->all();
        $rows['any'] = ['label' => 'Any Available Table', 'seats' => null, 'items' => []];

        foreach ($reservations->where('type', 'table') as $reservation) {
            $start = $reservation->reservation_at->max($open);
            $end = ($reservation->reservation_end_at ?? $reservation->reservation_at->copy()->addMinutes(config('reservations.duration_minutes')))->min($close);
            if ($start >= $close) {
                continue;
            }
            $key = $reservation->dining_table_id && isset($rows[$reservation->dining_table_id]) ? $reservation->dining_table_id : 'any';
            $rows[$key]['items'][] = [
                'reservation' => $reservation,
                'start' => $start,
                'end' => $end,
                'left' => round($open->diffInMinutes($start) / $span * 100, 3),
                'width' => round(max(15, $start->diffInMinutes($end)) / $span * 100, 3),
            ];
        }

        foreach ($rows as $key => $row) {
            $laneEnds = [];
            foreach ($row['items'] as $index => $item) {
                $lane = collect($laneEnds)->search(fn ($laneEnd) => $laneEnd <= $item['start']);
                $lane = $lane === false ? count($laneEnds) : $lane;
                $laneEnds[$lane] = $item['end'];
                $rows[$key]['items'][$index]['lane'] = $lane;
            }
            $rows[$key]['lanes'] = max(1, count($laneEnds));
            $rows[$key]['conflict'] = $key !== 'any' && count($laneEnds) > 1;
        }

        if ($rows['any']['items'] === []) {
            unset($rows['any']);
        }

        return [
            'rows' => $rows,
            'exclusive' => $reservations->where('type', 'exclusive')->values(),
            'hours' => collect(range(0, intdiv($span, 60)))->map(fn (int $hour) => $open->copy()->addHours($hour)),
            'span' => $span,
            'now' => $date->isToday() && now()->between($open, $close)
                ? round($open->diffInMinutes(now()) / $span * 100, 3)
                : null,
            'count' => $reservations->count(),
        ];
    }

    public function proof(Request $request, Reservation $reservation)
    {
        $canView = $request->user()->hasRole('super_admin', 'admin')
            || $reservation->user_id === $request->user()->id;

        abort_unless($canView, 403);
        abort_unless($reservation->payment_proof_path, 404);

        return Storage::disk('local')->response($reservation->payment_proof_path);
    }

    public function updateStatus(
        UpdateReservationStatusRequest $request,
        Reservation $reservation,
    ): RedirectResponse {
        app(ReservationSchedule::class)->changeStatus($reservation, $request->validated('status'), $request->user()->id);

        $message = match ($reservation->status) {
            'confirmed' => 'Reservation approved successfully.',
            'completed' => 'Reservation marked as completed.',
            'cancelled' => 'Reservation cancelled.',
            default => 'Reservation status updated.',
        };

        return back()->with('status', $message);
    }

    private function newReference(): string
    {
        do {
            $reference = 'KRM-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
        } while (Reservation::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
