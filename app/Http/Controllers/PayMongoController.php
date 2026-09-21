<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PayMongoCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PayMongoController extends Controller
{
    public function checkout(Request $request, Order $order, PayMongoCheckout $checkout): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        try {
            return redirect()->away($checkout->urlFor($order));
        } catch (Throwable $exception) {
            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            report($exception);

            return redirect()->route('shop.orders.show', $order)
                ->with('payment_error', 'PayMongo checkout is unavailable right now. Please try again.');
        }
    }
}
