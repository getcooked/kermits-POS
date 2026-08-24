<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class MobileRegistrationController extends Controller
{
    public function sendCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160', 'regex:/^[^@\s]+@gmail\.com$/i', 'unique:users,email'],
        ]);
        $email = Str::lower($validated['email']);
        $code = (string) random_int(100000, 999999);
        $challenge = Str::random(64);

        Cache::put($this->challengeKey($challenge), [
            'email' => $email,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(10));

        Mail::raw("Your Kermit's verification code is {$code}. It expires in 10 minutes.", function ($message) use ($email): void {
            $message->to($email)->subject("Kermit's account verification code");
        });

        return response()->json(['data' => [
            'challenge' => $challenge,
            'email' => $email,
            'expires_in' => 600,
        ]]);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge' => ['required', 'string', 'size:64'],
            'email' => ['required', 'email', 'max:160'],
            'code' => ['required', 'digits:6'],
        ]);
        $key = $this->challengeKey($validated['challenge']);
        $challenge = Cache::get($key);

        if (! $challenge || ! hash_equals($challenge['email'], Str::lower($validated['email']))) {
            return response()->json(['message' => 'The verification request has expired. Please request a new code.'], 422);
        }

        if (($challenge['attempts'] ?? 0) >= 5) {
            Cache::forget($key);

            return response()->json(['message' => 'Too many incorrect attempts. Please request a new code.'], 422);
        }

        if (! Hash::check($validated['code'], $challenge['code_hash'])) {
            $challenge['attempts'] = ($challenge['attempts'] ?? 0) + 1;
            Cache::put($key, $challenge, now()->addMinutes(10));

            return response()->json(['message' => 'The verification code is incorrect.'], 422);
        }

        Cache::forget($key);
        $registrationToken = Str::random(64);
        Cache::put($this->registrationKey($registrationToken), $challenge['email'], now()->addMinutes(15));

        return response()->json(['data' => [
            'registration_token' => $registrationToken,
            'email' => $challenge['email'],
            'expires_in' => 900,
        ]]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registration_token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:160', 'regex:/^[^@\s]+@gmail\.com$/i', 'unique:users,email'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $key = $this->registrationKey($validated['registration_token']);
        $verifiedEmail = Cache::get($key);
        $email = Str::lower($validated['email']);

        if (! $verifiedEmail || ! hash_equals($verifiedEmail, $email)) {
            return response()->json(['message' => 'Email verification has expired. Please verify your Gmail address again.'], 422);
        }

        $user = User::query()->create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $email,
            'phone' => $validated['phone'],
            'role' => User::ROLE_CUSTOMER,
            'password' => $validated['password'],
            'email_verified_at' => now(),
        ]);
        Cache::forget($key);

        return response()->json(['data' => $user->only([
            'id', 'name', 'username', 'email', 'phone', 'role',
        ])], 201);
    }

    private function challengeKey(string $challenge): string
    {
        return 'mobile-registration-challenge:'.hash('sha256', $challenge);
    }

    private function registrationKey(string $token): string
    {
        return 'mobile-registration-token:'.hash('sha256', $token);
    }
}
