<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCustomerPasswordRequest;
use App\Http\Requests\UpdateCustomerProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function editProfile(Request $request): View
    {
        return view('customer.profile', [
            'customer' => $request->user(),
            'accountSection' => $request->query('section') === 'password' ? 'password' : 'personal',
        ]);
    }

    public function updateProfile(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('status', 'Your profile was updated.');
    }

    public function editSettings(Request $request): RedirectResponse
    {
        return redirect()->route('customer.profile.edit', ['section' => 'password']);
    }

    public function updatePassword(UpdateCustomerPasswordRequest $request): RedirectResponse
    {
        $customer = $request->user();
        $currentSessionId = $request->session()->getId();

        $customer->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $customer->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
        DB::table('mobile_api_tokens')->where('user_id', $customer->id)->delete();
        DB::table('password_reset_tokens')->where('email', $customer->email)->delete();

        $request->session()->regenerate();

        return back()->with('status', 'Your password was changed. Other web and mobile sessions were signed out.');
    }
}
