<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class CustomerNotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.notifications', [
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless(
            $notification->notifiable_type === $request->user()::class
                && (int) $notification->notifiable_id === (int) $request->user()->id,
            403,
        );

        $notification->markAsRead();

        $type = $notification->data['subject_type'] ?? null;
        $id = (int) ($notification->data['subject_id'] ?? 0);

        return match ($type) {
            'reservation' => redirect()->route('reservations.show', $id),
            'order' => redirect()->route('shop.orders.show', $id),
            default => redirect()->route('customer.notifications.index'),
        };
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'All notifications were marked as read.');
    }
}
