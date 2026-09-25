<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SystemSetting;
use App\Services\OrderReceiptPdf;
use App\Services\OrderService;
use App\Services\PayMongoCheckout;
use App\Services\ReservationPricing;
use App\Services\ReservationSchedule;
use App\Services\TableLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CustomerOrderController extends Controller
{
    public function index(ReservationPricing $pricing, TableLayout $tables): View
    {
        $qrPath = SystemSetting::get('gcash_qr_path');
        $disk = Storage::disk('public');
        $qrImage = $qrPath && $disk->exists($qrPath) ? $disk->get($qrPath) : null;
        $qrMime = $qrImage ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($qrImage) : null;
        $gcashQrSrc = $qrImage && in_array($qrMime, ['image/png', 'image/jpeg', 'image/webp'], true)
            ? 'data:'.$qrMime.';base64,'.base64_encode($qrImage)
            : null;

        return view('shop.index', [
            'products' => Product::query()->available()->where('stock', '>', 0)->menuOrder()->get(),
            'gcashQrSrc' => $gcashQrSrc,
            'tableFees' => $pricing->tableFees(),
            'diningTables' => $tables->activeTables()->sortBy('number')->values(),
            'paymongoEnabled' => PayMongoCheckout::enabled(),
        ]);
    }

    public function store(OrderRequest $request, OrderService $orders, ReservationSchedule $schedules, PayMongoCheckout $checkout, ReservationPricing $pricing): RedirectResponse
    {
        $paymentMethod = $request->validated('payment_method');
        $paymentReference = $paymentMethod === 'gcash'
            ? $request->validated('payment_reference')
            : null;
        $proofPath = $paymentMethod === 'gcash' && $request->hasFile('payment_proof')
            ? $request->file('payment_proof')->store('payment-proofs', 'local')
            : null;

        try {
            $order = DB::transaction(function () use ($request, $orders, $paymentMethod, $paymentReference, $proofPath, $schedules, $pricing): Order {
                $schedules->lock();
                $order = $orders->create(
                    user: $request->user(),
                    quantities: $request->selectedQuantities(),
                    paymentStatus: 'pending',
                    paymentMethod: $paymentMethod,
                    paymentReference: $paymentReference,
                    customer: $request->user(),
                );

                $tableSize = (int) $request->validated('table_size');
                $reservationFee = $pricing->tableFee($tableSize);
                $reservation = $schedules->reserve([
                    'user_id' => $request->user()->id,
                    'order_id' => $order->id,
                    'reference' => $this->newReservationReference(),
                    'type' => 'table',
                    'table_size' => $tableSize,
                    'dining_table_id' => $request->validated('dining_table_id'),
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

        session()->flash('clear_customer_cart', true);

        if ($paymentMethod === 'paymongo') {
            try {
                return redirect()->away($checkout->urlFor($order));
            } catch (Throwable $exception) {
                report($exception);

                return redirect()->route('shop.orders.show', $order)
                    ->with('payment_error', 'Your order was saved, but PayMongo checkout is unavailable. Please try the payment link on your order.');
            }
        }

        $status = match ($paymentMethod) {
            'cash' => 'Pay at the counter when you arrive. Your order receipt is ready.',
            'gcash' => 'Your payment details are awaiting verification. Your order receipt is ready.',
            default => 'Your reservation was submitted. Your order receipt is ready.',
        };

        return redirect()
            ->route('shop.orders.show', $order)
            ->with('status', $status);
    }

    public function show(Order $order): View
    {
        abort_unless($order->user_id === request()->user()->id, 403);

        return view('shop.show', [
            'order' => $order->load(['items.product', 'reservation']),
        ]);
    }

    public function receipt(Order $order, OrderReceiptPdf $receipt): Response
    {
        abort_unless($order->user_id === request()->user()->id, 403);

        $filename = 'Kermits-Receipt-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT).'.pdf';

        return response($receipt->render($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
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
