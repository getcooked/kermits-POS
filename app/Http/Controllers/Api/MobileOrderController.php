<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Reservation;
use App\Rules\ReservationHours;
use App\Services\OrderService;
use App\Services\ReservationPricing;
use App\Services\ReservationSchedule;
use App\Services\TableLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MobileOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()->with(['items.product', 'reservation'])->where('customer_id', $request->user()->id)
            ->latest()->get()->map(fn (Order $order): array => $this->data($order));

        return response()->json(['data' => $orders]);
    }

    public function store(Request $request, OrderService $orders, ReservationSchedule $schedules, ReservationPricing $pricing, TableLayout $tables): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'payment_method' => ['required', 'in:cash'],
            'payment_reference' => ['prohibited'],
            'payment_proof' => ['prohibited'],
            'table_size' => ['nullable', 'required_with:phone,reservation_at', 'integer', Rule::in($pricing->tableSizes())],
            'dining_table_id' => $tables->requestRules(),
            'phone' => ['nullable', 'required_with:table_size,reservation_at', 'regex:/^09\d{9}$/'],
            'reservation_at' => ['nullable', 'required_with:table_size,phone', 'bail', 'date', 'after:now', new ReservationHours],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $quantities = collect($validated['items'])->mapWithKeys(fn (array $item): array => [(int) $item['product_id'] => (int) $item['quantity']])->all();
        $needsReservation = isset($validated['table_size'], $validated['phone'], $validated['reservation_at']);
        $tableId = isset($validated['dining_table_id']) ? (int) $validated['dining_table_id'] : null;
        if ($needsReservation && ! $schedules->isAvailable($validated['reservation_at'], 'table', (int) $validated['table_size'], $tableId)) {
            throw ValidationException::withMessages([
                'reservation_at' => 'This reservation time is no longer available. Please choose another schedule.',
            ]);
        }

        $proofPath = null;

        try {
            $order = DB::transaction(function () use ($request, $orders, $validated, $quantities, $proofPath, $needsReservation, $schedules, $pricing, $tableId): Order {
                $schedules->lock();
                $order = $orders->create(
                    user: $request->user(), quantities: $quantities, paymentStatus: 'pending',
                    paymentMethod: $validated['payment_method'], paymentReference: $validated['payment_reference'] ?? null,
                    customer: $request->user(),
                );

                if ($needsReservation) {
                    $tableSize = (int) $validated['table_size'];
                    $reservationFee = $pricing->tableFee($tableSize);
                    $reservation = $schedules->reserve([
                        'user_id' => $request->user()->id,
                        'order_id' => $order->id,
                        'reference' => $this->newReservationReference(),
                        'type' => 'table',
                        'table_size' => $tableSize,
                        'dining_table_id' => $tableId,
                        'customer_name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'phone' => $validated['phone'],
                        'reservation_at' => $schedules->normalize($validated['reservation_at']),
                        'guests' => $tableSize,
                        'reservation_fee' => $reservationFee,
                        'food_total' => 0,
                        'total_amount' => $reservationFee,
                        'payment_method' => $validated['payment_method'],
                        'payment_reference' => $validated['payment_reference'] ?? null,
                        'payment_status' => 'pending',
                        'payment_proof_path' => $proofPath,
                        'notes' => $validated['notes'] ?? null,
                        'status' => 'pending',
                    ]);

                    $reservation->statusHistories()->create([
                        'from_status' => null,
                        'to_status' => 'pending',
                        'changed_by' => $request->user()->id,
                    ]);
                }

                return $order;
            })->load(['items.product', 'reservation']);
        } catch (Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
        }

        return response()->json(['data' => $this->data($order)], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        return response()->json(['data' => $this->data($order->load(['items.product', 'reservation']))]);
    }

    private function data(Order $order): array
    {
        return [
            'id' => $order->id, 'total' => (float) $order->total, 'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status, 'payment_reference' => $order->payment_reference,
            'cash_received' => $order->cash_received !== null ? (float) $order->cash_received : null,
            'change_due' => $order->change_due !== null ? (float) $order->change_due : null,
            'total_due' => $order->totalDue(),
            'created_at' => $order->created_at?->toIso8601String(),
            'reservation' => $order->reservation ? [
                'id' => $order->reservation->id, 'reference' => $order->reservation->reference,
                'type' => $order->reservation->type, 'table_size' => $order->reservation->table_size,
                'dining_table_id' => $order->reservation->dining_table_id, 'table_label' => $order->reservation->table_label,
                'guests' => $order->reservation->guests,
                'reservation_at' => $order->reservation->reservation_at?->toIso8601String(),
                'reservation_end_at' => $order->reservation->reservation_end_at?->toIso8601String(),
                'hold_expires_at' => $order->reservation->hold_expires_at?->toIso8601String(),
                'phone' => $order->reservation->phone,
                'reservation_fee' => (float) $order->reservation->reservation_fee,
                'food_total' => (float) $order->reservation->food_total,
                'total_amount' => (float) $order->reservation->total_amount,
                'payment_method' => $order->reservation->payment_method,
                'payment_status' => $order->reservation->payment_status,
                'payment_reference' => $order->reservation->payment_reference,
                'food_request' => $order->reservation->food_request,
                'notes' => $order->reservation->notes,
                'status' => $order->reservation->booking_status,
                'items' => [],
            ] : null,
            'items' => $order->items->map(fn ($item): array => [
                'product_id' => $item->product_id, 'name' => $item->product?->name ?? 'Product',
                'quantity' => $item->quantity, 'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ])->values(),
        ];
    }

    private function newReservationReference(): string
    {
        do {
            $reference = 'KRM-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
        } while (Reservation::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
