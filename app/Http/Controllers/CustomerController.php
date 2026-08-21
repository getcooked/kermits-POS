<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCustomerAccountRequest;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        $customers = User::query()
            ->where('role', User::ROLE_CUSTOMER)
            ->withCount(['purchases as orders_count'])
            ->withSum(['purchases as orders_sum_total'], 'total')
            ->latest()
            ->get();

        $reservationCounts = Reservation::query()
            ->selectRaw('email, COUNT(*) as total')
            ->groupBy('email')
            ->pluck('total', 'email');

        return view('customers.index', compact('customers', 'reservationCounts'));
    }

    public function show(User $customer): View
    {
        abort_unless($customer->hasRole(User::ROLE_CUSTOMER), 404);

        $customer->load([
            'purchases' => fn ($query) => $query->with('items.product')->latest(),
            'reservations' => fn ($query) => $query
                ->with(['items.product', 'statusHistories.changedBy'])
                ->latest('reservation_at'),
        ]);

        $customer->setRelation('orders', $customer->purchases);

        return view('customers.show', compact('customer'));
    }

    public function update(UpdateCustomerAccountRequest $request, User $customer): RedirectResponse
    {
        abort_unless($customer->hasRole(User::ROLE_CUSTOMER), 404);

        $data = $request->safe()->except(['password', 'password_confirmation']);
        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $customer->update($data);

        return back()->with('status', 'Customer account updated securely.');
    }

    public function destroy(User $customer): RedirectResponse
    {
        abort_unless(request()->user()->hasRole(User::ROLE_SUPER_ADMIN), 403);
        abort_unless($customer->hasRole(User::ROLE_CUSTOMER), 404);

        DB::transaction(function () use ($customer): void {
            $deletedIdentity = 'deleted-'.$customer->id.'-'.Str::lower(Str::random(10));

            foreach ($customer->reservations()->get() as $reservation) {
                if ($reservation->payment_proof_path) {
                    Storage::disk('local')->delete($reservation->payment_proof_path);
                }
                $reservation->update([
                    'customer_name' => 'Deleted Customer',
                    'email' => $deletedIdentity.'@invalid.local',
                    'phone' => '09000000000',
                    'payment_proof_path' => null,
                ]);
            }

            $customer->forceFill([
                'name' => 'Deleted Customer #'.$customer->id,
                'username' => $deletedIdentity,
                'email' => $deletedIdentity.'@invalid.local',
                'phone' => null,
                'password' => Str::random(64),
                'remember_token' => null,
            ])->save();
            $customer->delete();
        });

        return redirect()->route('customers.index')->with('status', 'Customer account deleted. Transaction records were retained without personal account details.');
    }
}
