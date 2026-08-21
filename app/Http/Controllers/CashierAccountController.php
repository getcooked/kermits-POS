<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashierAccountRequest;
use App\Http\Requests\UpdateCashierAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CashierAccountController extends Controller
{
    public function index(): View
    {
        return view('staff.cashiers', [
            'cashiers' => User::query()
                ->where('role', User::ROLE_CASHIER)
                ->withCount(['orders as paid_sales_count' => fn ($query) => $query->where('payment_status', 'paid')])
                ->withSum(['orders as paid_sales_total' => fn ($query) => $query->where('payment_status', 'paid')], 'total')
                ->latest()
                ->get(),
        ]);
    }

    public function store(StoreCashierAccountRequest $request): RedirectResponse
    {
        User::query()->create([
            ...$request->validated(),
            'role' => User::ROLE_CASHIER,
        ]);

        return back()->with('status', 'Cashier account created successfully.');
    }

    public function update(UpdateCashierAccountRequest $request, User $cashier): RedirectResponse
    {
        abort_unless($cashier->hasRole(User::ROLE_CASHIER), 404);

        $data = $request->safe()->except(['password', 'password_confirmation']);
        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }
        $cashier->update($data);

        return back()->with('status', 'Cashier account updated successfully.');
    }

    public function destroy(User $cashier): RedirectResponse
    {
        abort_unless(request()->user()->hasRole(User::ROLE_SUPER_ADMIN), 403);
        abort_unless($cashier->hasRole(User::ROLE_CASHIER), 404);

        $deletedIdentity = 'deleted-cashier-'.$cashier->id.'-'.Str::lower(Str::random(10));
        $cashier->forceFill([
            'name' => 'Deleted Cashier #'.$cashier->id,
            'username' => $deletedIdentity,
            'email' => $deletedIdentity.'@invalid.local',
            'phone' => null,
            'password' => Str::random(64),
            'remember_token' => null,
        ])->save();
        $cashier->delete();

        return back()->with('status', 'Cashier account deleted. Sales and audit records were retained.');
    }
}
