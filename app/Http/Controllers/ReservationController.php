<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationStatusRequest;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ReservationController extends Controller
{
    private const TABLE_FEES = [1 => 100, 2 => 150, 4 => 250, 8 => 450, 12 => 650];

    private const EXCLUSIVE_FEE = 5000;

    public function create(Request $request): View
    {
        return view('reservations.create', [
            'products' => Product::query()->available()->where('stock', '>', 0)->menuOrder()->get(),
            'tableFees' => self::TABLE_FEES,
            'exclusiveFee' => self::EXCLUSIVE_FEE,
            'gcashQrPath' => SystemSetting::get('gcash_qr_path'),
        ]);
    }

    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $proofPath = $request->hasFile('payment_proof')
            ? $request->file('payment_proof')->store('payment-proofs', 'local')
            : null;

        try {
            $reservation = DB::transaction(function () use ($request, $proofPath): Reservation {
                $reservationFee = $request->validated('type') === 'table'
                    ? self::TABLE_FEES[(int) $request->validated('table_size')]
                    : self::EXCLUSIVE_FEE;
                $reservation = Reservation::query()->create([
                    ...$request->safe()->except(['menu_items', 'payment_proof']),
                    'user_id' => $request->user()->id,
                    'customer_name' => $request->user()->name,
                    'email' => $request->user()->email,
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

                $reservation->update(['food_total' => $foodTotal, 'total_amount' => $reservationFee + $foodTotal]);

                $reservation->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => 'pending',
                    'changed_by' => $request->user()->id,
                ]);

                return $reservation;
            });
        } catch (Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
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
            'status' => ['nullable', 'in:pending,confirmed,completed,cancelled'],
            'type' => ['nullable', 'in:table,exclusive'],
        ]);

        $reservations = Reservation::query()
            ->with(['handler', 'items.product'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->orderBy('reservation_at')
            ->get();

        return view('reservations.index', compact('reservations'));
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
        if ($request->validated('status') === 'confirmed' && $reservation->type === 'table') {
            $overlappingTables = Reservation::query()
                ->whereKeyNot($reservation->id)
                ->where('type', 'table')
                ->where('status', 'confirmed')
                ->whereBetween('reservation_at', [
                    $reservation->reservation_at->copy()->subMinutes(119),
                    $reservation->reservation_at->copy()->addMinutes(119),
                ])
                ->count();

            if ($overlappingTables >= 8) {
                throw ValidationException::withMessages([
                    'status' => 'All 8 tables are already reserved for this time slot. Choose another schedule before approving.',
                ]);
            }
        }

        $previousStatus = $reservation->status;
        DB::transaction(function () use ($request, $reservation, $previousStatus): void {
            $reservation->update([
                'status' => $request->validated('status'),
                'handled_by' => $request->user()->id,
            ]);
            $reservation->statusHistories()->create([
                'from_status' => $previousStatus,
                'to_status' => $reservation->status,
                'changed_by' => $request->user()->id,
            ]);
        });

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
