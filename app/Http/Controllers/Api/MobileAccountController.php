<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\CustomerPasswordVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class MobileAccountController extends Controller
{
    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($customer),
            ],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
        ], [
            'username.regex' => 'The username may only contain letters, numbers, dots, underscores, and hyphens.',
            'phone.regex' => 'Enter an 11-digit Philippine mobile number starting with 09.',
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Your personal information was updated.',
            'data' => $customer->fresh()->only(['id', 'name', 'username', 'email', 'phone', 'role']),
        ]);
    }

    public function sendPasswordVerificationCode(Request $request): JsonResponse
    {
        $customer = $request->user();
        $code = (string) random_int(100000, 999999);
        $key = $this->verificationKey($customer->getKey());

        Cache::put($key, [
            'email' => strtolower($customer->email),
            'code_hash' => Hash::make($code),
        ], now()->addMinutes(10));

        try {
            $customer->notify(new CustomerPasswordVerification($code));
        } catch (Throwable $exception) {
            Cache::forget($key);

            throw $exception;
        }

        return response()->json([
            'message' => 'A 6-digit verification code was sent to '.$customer->email.'.',
            'data' => [
                'email' => $customer->email,
                'expires_in' => 600,
            ],
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_code' => ['required', 'digits:6'],
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'verification_code.required' => 'Enter the verification code sent to your email.',
            'password.different' => 'Choose a new password that is different from your current password.',
        ]);
        $customer = $request->user();

        if (! Hash::check($validated['current_password'], $customer->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $key = $this->verificationKey($customer->getKey());
        $verification = Cache::get($key);
        $codeHash = is_array($verification) ? ($verification['code_hash'] ?? null) : null;
        $validCode = is_array($verification)
            && hash_equals(strtolower((string) ($verification['email'] ?? '')), strtolower($customer->email))
            && is_string($codeHash)
            && $codeHash !== ''
            && Hash::check($validated['verification_code'], $codeHash);

        if (! $validCode) {
            throw ValidationException::withMessages([
                'verification_code' => 'The email verification code is invalid or has expired. Request a new code.',
            ]);
        }

        DB::transaction(function () use ($customer, $validated): void {
            $customer->forceFill(['password' => $validated['password']])->save();
            DB::table('mobile_api_tokens')->where('user_id', $customer->id)->delete();
            DB::table('password_reset_tokens')->where('email', $customer->email)->delete();
        });
        Cache::forget($key);

        return response()->json([
            'message' => 'Your password was changed. Please log in again with your new password.',
        ]);
    }

    private function verificationKey(int $customerId): string
    {
        return 'mobile-customer-password-verification:'.$customerId;
    }
}
