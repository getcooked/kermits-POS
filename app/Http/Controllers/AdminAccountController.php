<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetAdminPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminAccountController extends Controller
{
    public function index(): View
    {
        return view('staff.admins', [
            'admins' => User::query()
                ->where('role', User::ROLE_ADMIN)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function updatePassword(ResetAdminPasswordRequest $request, User $admin): RedirectResponse
    {
        abort_unless($admin->hasRole(User::ROLE_ADMIN), 404);

        $admin->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table(config('session.table', 'sessions'))->where('user_id', $admin->id)->delete();
        DB::table('password_reset_tokens')->where('email', $admin->email)->delete();

        return back()->with('status', "Password changed for {$admin->name}. Their active sessions were signed out.");
    }
}
