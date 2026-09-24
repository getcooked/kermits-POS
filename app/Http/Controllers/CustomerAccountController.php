<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCustomerPasswordRequest;
use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Notifications\CustomerPasswordVerification;
use App\Services\CustomerDetailsSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CustomerAccountController extends Controller
{
    public function editProfile(Request $request): View
    {
        return view('customer.profile', [
            'customer' => $request->user(),
            'accountSection' => $request->query('section') === 'password' ? 'password' : 'personal',
        ]);
    }

    public function updateProfile(
        UpdateCustomerProfileRequest $request,
        CustomerDetailsSchema $customerDetailsSchema,
    ): RedirectResponse {
        $customerDetailsSchema->ensure();
        $request->user()->update($request->validated());

        return back()->with('status', 'Your profile was updated.');
    }

    public function editSettings(Request $request): RedirectResponse
    {
        return redirect()->route('customer.profile.edit', ['section' => 'password']);
    }

    public function sendPasswordVerificationCode(Request $request): RedirectResponse
    {
        $customer = $request->user();
        $code = (string) random_int(100000, 999999);

        $request->session()->put('customer_password_verification', [
            'user_id' => $customer->getKey(),
            'email' => strtolower($customer->email),
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        try {
            $customer->notify(new CustomerPasswordVerification($code));
        } catch (Throwable $exception) {
            $request->session()->forget('customer_password_verification');
            report($exception);

            return back()->withErrors(['email' => 'The verification email could not be sent. Please try again later.']);
        }

        return back()->with('verification_sent', 'A 6-digit verification code was sent to '.$customer->email.'.');
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

        $request->session()->forget('customer_password_verification');
        $request->session()->regenerate();

        return back()->with('status', 'Your password was changed. Other web and mobile sessions were signed out.');
    }
}
