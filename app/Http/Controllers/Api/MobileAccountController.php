<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\CustomerPasswordVerification;
use App\Services\CustomerDetailsSchema;
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
    public function updateProfile(Request $request, CustomerDetailsSchema $customerDetailsSchema): JsonResponse
    {
        $customer = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($customer),
            ],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'address' => ['sometimes', 'required', 'string', 'max:500'],
        ], [
            'username.regex' => 'The username may only contain letters, numbers, dots, underscores, and hyphens.',
            'phone.regex' => 'Enter an 11-digit Philippine mobile number starting with 09.',
        ]);

        $customerDetailsSchema->ensure();
        $customer->update($validated);
        $customer = $customer->fresh();

        return response()->json([
            'message' => 'Your personal information was updated.',
            'data' => [
                ...$customer->only(['id', 'name', 'username', 'email', 'phone', 'birthday', 'sex', 'address', 'role']),
                'birthday' => $customer->birthday?->format('Y-m-d'),
                'age' => $customer->birthday?->age,
            ],
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
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10)->timestamp,
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
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'verification_code.required' => 'Enter the verification code sent to your email.',
        ]);
        $customer = $request->user();

        $key = $this->verificationKey($customer->getKey());
        $verification = Cache::get($key);
        $codeHash = is_array($verification) ? ($verification['code_hash'] ?? null) : null;
        $validCode = is_array($verification)
            && hash_equals(strtolower((string) ($verification['email'] ?? '')), strtolower($customer->email))
            && is_string($codeHash)
            && $codeHash !== ''
            && Hash::check($validated['verification_code'], $codeHash);

        if (! $validCode) {
            if (is_array($verification)) {
                $verification['attempts'] = (int) ($verification['attempts'] ?? 0) + 1;
                if ($verification['attempts'] >= 5) {
                    Cache::forget($key);
                } else {
                    $remainingSeconds = max(1, (int) ($verification['expires_at'] ?? now()->timestamp) - now()->timestamp);
                    Cache::put($key, $verification, $remainingSeconds);
                }
            }

            $message = is_array($verification) && (int) ($verification['attempts'] ?? 0) >= 5
                ? 'Too many incorrect attempts. Request a new verification code.'
                : 'The email verification code is invalid or has expired. Request a new code.';
            throw ValidationException::withMessages([
                'verification_code' => $message,
            ]);
        }

        if (Hash::check($validated['password'], $customer->password)) {
            throw ValidationException::withMessages([
                'password' => 'Choose a new password that is different from your current password.',
            ]);
        }

        DB::transaction(function () use ($customer, $validated): void {
            $customer->forceFill(['password' => $validated['password']])->save();
            DB::table(config('session.table', 'sessions'))->where('user_id', $customer->id)->delete();
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
