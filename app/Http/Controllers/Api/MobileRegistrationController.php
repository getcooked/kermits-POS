<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MobileRegistrationController extends Controller
{
    public function sendCode(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);
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
            'expires_at' => now()->addMinutes(10)->timestamp,
        ], now()->addMinutes(10));

        try {
            Mail::raw("Your Kermit's verification code is {$code}. It expires in 10 minutes.", function ($message) use ($email): void {
                $message->to($email)->subject("Kermit's account verification code");
            });
        } catch (TransportExceptionInterface) {
            Cache::forget($this->challengeKey($challenge));
            Log::warning('Mobile registration email delivery failed.');

            return response()->json(['message' => 'The verification email could not be sent. Please try again later.'], 503);
        }

        return response()->json(['data' => [
            'challenge' => $challenge,
            'email' => $email,
            'expires_in' => 600,
        ]]);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);
        $validated = $request->validate([
            'challenge' => ['required', 'string', 'size:64'],
            'email' => ['required', 'email', 'max:160'],
            'code' => ['required', 'digits:6'],
        ]);
        $key = $this->challengeKey($validated['challenge']);
        $challenge = Cache::get($key);

        if (! $challenge || ! hash_equals($challenge['email'], Str::lower($validated['email']))) {
            return response()->json(['code' => 'verification_expired', 'message' => 'The verification request has expired. Please request a new code.'], 422);
        }

        if (($challenge['attempts'] ?? 0) >= 5) {
            Cache::forget($key);

            return response()->json(['code' => 'verification_attempts_exceeded', 'message' => 'Too many incorrect attempts. Please request a new code.'], 422);
        }

        if (! Hash::check($validated['code'], $challenge['code_hash'])) {
            $challenge['attempts'] = ($challenge['attempts'] ?? 0) + 1;
            $remainingSeconds = max(1, ($challenge['expires_at'] ?? now()->addMinutes(10)->timestamp) - now()->timestamp);
            Cache::put($key, $challenge, $remainingSeconds);

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
        $this->normalizeEmail($request);
        $validated = $request->validate([
            'registration_token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:30', 'regex:/^\p{L}[\p{L}\p{M}]*(?: \p{L}[\p{L}\p{M}]*)*$/u'],
            'username' => ['required', 'string', 'min:3', 'max:13', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:160', 'regex:/^[^@\s]+@gmail\.com$/i', 'unique:users,email'],
            'phone' => ['required', 'string', 'size:11', 'regex:/^09[0-9]{9}$/'],
            'birthday' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'sex' => ['required', 'in:male,female,prefer_not_to_say'],
            'address' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()->max(23)],
        ], [
            'name.max' => 'The full name must not be more than 30 characters.',
            'name.regex' => 'The full name may only contain letters and single spaces.',
            'username.max' => 'The username must not be more than 13 characters.',
            'phone.size' => 'The phone number must contain exactly 11 digits.',
            'phone.regex' => 'The phone number must contain exactly 11 digits and start with 09.',
            'password.max' => 'The password must not be more than 23 characters.',
        ]);
        $key = $this->registrationKey($validated['registration_token']);
        $verifiedEmail = Cache::get($key);
        $email = Str::lower($validated['email']);

        if (! $verifiedEmail || ! hash_equals($verifiedEmail, $email)) {
            return response()->json(['code' => 'verification_expired', 'message' => 'Email verification has expired. Please verify your Gmail address again.'], 422);
        }

        $user = User::query()->create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $email,
            'phone' => $validated['phone'],
            'birthday' => $validated['birthday'],
            'sex' => $validated['sex'],
            'address' => $validated['address'],
            'role' => User::ROLE_CUSTOMER,
            'password' => $validated['password'],
            'email_verified_at' => now(),
        ]);
        Cache::forget($key);

        return response()->json(['data' => [
            ...$user->only(['id', 'name', 'username', 'email', 'phone', 'sex', 'address', 'role']),
            'birthday' => $user->birthday?->format('Y-m-d'),
            'age' => $user->birthday?->age,
        ]], 201);
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
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
