<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerNotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.notifications', [
            'orderNotifications' => $request->user()
                ->purchases()
                ->with('items.product')
                ->whereIn('payment_status', ['paid', 'rejected'])
                ->latest('updated_at')
                ->get(),
        ]);
    }
}
