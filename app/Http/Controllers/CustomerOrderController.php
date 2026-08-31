<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SystemSetting;
use App\Services\OrderService;
use App\Services\ReservationSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CustomerOrderController extends Controller
{
    private const TABLE_FEES = [1 => 100, 2 => 150, 4 => 250, 8 => 450, 12 => 650];

    public function index(): View
    {
        return view('shop.index', [
            'products' => Product::query()->available()->where('stock', '>', 0)->menuOrder()->get(),
            'gcashQrPath' => SystemSetting::get('gcash_qr_path'),
            'tableFees' => self::TABLE_FEES,
        ]);
    }

    public function store(OrderRequest $request, OrderService $orders, ReservationSchedule $schedules): RedirectResponse
    {
        $paymentMethod = $request->validated('payment_method');
        $paymentReference = $paymentMethod === 'gcash'
            ? $request->validated('payment_reference')
            : null;
        $proofPath = $paymentMethod === 'gcash' && $request->hasFile('payment_proof')
            ? $request->file('payment_proof')->store('payment-proofs', 'local')
            : null;

        try {
            $order = DB::transaction(function () use ($request, $orders, $paymentMethod, $paymentReference, $proofPath, $schedules): Order {
                $order = $orders->create(
                    user: $request->user(),
                    quantities: $request->selectedQuantities(),
                    paymentStatus: 'pending',
                    paymentMethod: $paymentMethod,
                    paymentReference: $paymentReference,
                    customer: $request->user(),
                );

                $tableSize = (int) $request->validated('table_size');
                $reservationFee = self::TABLE_FEES[$tableSize];
                $reservation = Reservation::query()->create([
                    'user_id' => $request->user()->id,
                    'order_id' => $order->id,
                    'reference' => $this->newReservationReference(),
                    'type' => 'table',
                    'table_size' => $tableSize,
                    'customer_name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'phone' => $request->validated('phone'),
                    'reservation_at' => $schedules->normalize($request->validated('reservation_at')),
                    'guests' => $tableSize,
                    'reservation_fee' => $reservationFee,
                    'food_total' => 0,
                    'total_amount' => $reservationFee,
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $paymentReference,
                    'payment_status' => 'pending',
                    'payment_proof_path' => $proofPath,
                    'food_request' => null,
                    'notes' => $request->validated('notes'),
                    'status' => 'pending',
                ]);

                $reservation->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => 'pending',
                    'changed_by' => $request->user()->id,
                ]);

                return $order;
            });
        } catch (Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
        }

        return redirect()
            ->route('shop.orders.show', $order)
            ->with('status', 'Reservation submitted and payment method selected. Your receipt is ready.');
    }

    public function show(Order $order): View
    {
        abort_unless($order->user_id === request()->user()->id, 403);

        return view('shop.show', [
            'order' => $order->load(['items.product', 'reservation']),
        ]);
    }

    private function newReservationReference(): string
    {
        do {
            $reference = 'KRM-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
        } while (Reservation::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
