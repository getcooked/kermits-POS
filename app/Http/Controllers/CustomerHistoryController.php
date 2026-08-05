<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user();

        return view('customer.history', [
            'reservations' => $customer->reservations()
                ->with(['items.product', 'statusHistories.changedBy'])
                ->latest('reservation_at')
                ->get(),
            'orders' => $customer->purchases()
                ->with('items.product')
                ->latest()
                ->get(),
        ]);
    }
}
