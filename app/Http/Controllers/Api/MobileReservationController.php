<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Reservation;
use App\Services\ReservationSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MobileReservationController extends Controller
{
    private const TABLE_FEES = [1 => 100, 2 => 150, 4 => 250, 8 => 450, 12 => 650];

    private const EXCLUSIVE_FEE = 5000;

    public function index(Request $request): JsonResponse
    {
        $items = Reservation::query()->with('items.product')->whereBelongsTo($request->user())
            ->latest('reservation_at')->get()->map(fn (Reservation $reservation): array => $this->data($reservation));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, ReservationSchedule $schedules): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:table,exclusive'],
            'table_size' => ['nullable', 'required_if:type,table', 'integer', 'in:1,2,4,8,12'],
            'phone' => ['required', 'regex:/^09\d{9}$/'], 'reservation_at' => ['required', 'date', 'after:now'],
            'guests' => ['nullable', 'required_if:type,exclusive', 'integer', 'min:1', 'max:300'],
            'food_request' => ['nullable', 'string', 'max:2000'], 'menu_items' => ['nullable', 'array'],
            'menu_items.*' => ['nullable', 'integer', 'min:0', 'max:22'], 'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', 'in:cash,gcash'],
            'payment_reference' => ['nullable', 'required_if:payment_method,gcash', 'digits:13'],
            'payment_proof' => ['nullable', 'required_if:payment_method,gcash', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        if (! $schedules->isAvailable($validated['reservation_at'])) {
            throw ValidationException::withMessages([
                'reservation_at' => 'This reservation time is no longer available. Please choose another schedule.',
            ]);
        }
        $proofPath = $request->hasFile('payment_proof') ? $request->file('payment_proof')->store('payment-proofs', 'local') : null;

        try {
            $reservation = DB::transaction(function () use ($request, $validated, $proofPath, $schedules): Reservation {
                $fee = $validated['type'] === 'table' ? self::TABLE_FEES[(int) $validated['table_size']] : self::EXCLUSIVE_FEE;
                $reservation = Reservation::query()->create([
                    ...collect($validated)->except(['menu_items', 'payment_proof'])->all(),
                    'user_id' => $request->user()->id, 'customer_name' => $request->user()->name,
                    'email' => $request->user()->email, 'reference' => $this->newReference(), 'status' => 'pending',
                    'reservation_at' => $schedules->normalize($validated['reservation_at']),
                    'guests' => $validated['type'] === 'table' ? $validated['table_size'] : $validated['guests'],
                    'reservation_fee' => $fee, 'food_total' => 0, 'total_amount' => $fee,
                    'payment_status' => 'pending', 'payment_proof_path' => $proofPath,
                ]);
                $quantities = collect($validated['menu_items'] ?? [])->map(fn ($quantity): int => (int) $quantity)->filter()->all();
                $products = Product::query()->available()->whereIn('id', array_keys($quantities))->get()->keyBy('id');
                if ($products->count() !== count($quantities)) {
                    throw ValidationException::withMessages(['menu_items' => 'One or more selected menu items are unavailable.']);
                }
                $items = collect($quantities)->map(function (int $quantity, int|string $productId) use ($products): array {
                    $product = $products->get((int) $productId);

                    return ['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $product->price, 'subtotal' => (float) $product->price * $quantity];
                })->values();
                $reservation->items()->createMany($items->all());
                $foodTotal = (float) $items->sum('subtotal');
                $reservation->update(['food_total' => $foodTotal, 'total_amount' => $fee + $foodTotal]);
                $reservation->statusHistories()->create(['from_status' => null, 'to_status' => 'pending', 'changed_by' => $request->user()->id]);

                return $reservation->load('items.product');
            });
        } catch (Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }

        return response()->json(['data' => $this->data($reservation)], 201);
    }

    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        return response()->json(['data' => $this->data($reservation->load('items.product'))]);
    }

    private function data(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id, 'reference' => $reservation->reference, 'type' => $reservation->type,
            'table_size' => $reservation->table_size, 'guests' => $reservation->guests,
            'reservation_at' => $reservation->reservation_at?->toIso8601String(), 'phone' => $reservation->phone,
            'reservation_fee' => (float) $reservation->reservation_fee, 'food_total' => (float) $reservation->food_total,
            'total_amount' => (float) $reservation->total_amount, 'payment_method' => $reservation->payment_method,
            'payment_status' => $reservation->payment_status, 'payment_reference' => $reservation->payment_reference,
            'food_request' => $reservation->food_request, 'notes' => $reservation->notes, 'status' => $reservation->status,
            'items' => $reservation->items->map(fn ($item): array => [
                'product_id' => $item->product_id, 'name' => $item->product?->name ?? 'Product', 'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price, 'subtotal' => (float) $item->subtotal,
            ])->values(),
        ];
    }

    private function newReference(): string
    {
        do {
            $reference = 'KRM-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
        } while (Reservation::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
