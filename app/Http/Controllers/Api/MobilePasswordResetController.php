<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\CustomerPasswordResetCode;
use App\Rules\MobileRecaptcha;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MobilePasswordResetController extends Controller
{
    private const RESET_CODE_MESSAGE = 'If an eligible account exists, a 6-digit password reset code has been sent.';

    private const EXPIRES_IN = 600;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160'],
            'recaptcha_token' => MobileRecaptcha::rules($request),
        ], MobileRecaptcha::messages());

        $email = Str::lower($validated['email']);
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('role', User::ROLE_CUSTOMER)
            ->first();
        $code = (string) random_int(100000, 999999);
        $challenge = Str::random(64);
        $key = $this->challengeKey($challenge);

        // Unknown emails still receive a challenge so the response does not
        // reveal which addresses belong to customer accounts.
        Cache::put($key, [
            'email' => $email,
            'user_id' => $user?->getKey(),
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addSeconds(self::EXPIRES_IN)->timestamp,
        ], self::EXPIRES_IN);

        if ($user) {
            try {
                $user->notify(new CustomerPasswordResetCode($code));
            } catch (TransportExceptionInterface) {
                Cache::forget($key);
                Log::warning('Mobile password reset email delivery failed.');
            }
        }

        return response()->json([
            'message' => self::RESET_CODE_MESSAGE,
            'data' => [
                'challenge' => $challenge,
                'email' => $email,
                'expires_in' => self::EXPIRES_IN,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge' => ['required', 'string', 'size:64'],
            'email' => ['required', 'email', 'max:160'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()->max(23)],
        ], [
            'password.max' => 'The password must not be more than 23 characters.',
        ]);
        $key = $this->challengeKey($validated['challenge']);
        $challenge = Cache::get($key);

        if (! is_array($challenge) || ! hash_equals($challenge['email'] ?? '', Str::lower($validated['email']))) {
            return response()->json(['code' => 'verification_expired', 'message' => 'The reset code has expired. Please request a new code.'], 422);
        }

        if (($challenge['attempts'] ?? 0) >= 5) {
            Cache::forget($key);

            return response()->json(['code' => 'verification_attempts_exceeded', 'message' => 'Too many incorrect attempts. Please request a new code.'], 422);
        }

        $user = isset($challenge['user_id'])
            ? User::query()->whereKey($challenge['user_id'])->where('role', User::ROLE_CUSTOMER)->first()
            : null;

        if (! $user || ! Hash::check($validated['code'], $challenge['code_hash'])) {
            $challenge['attempts'] = ($challenge['attempts'] ?? 0) + 1;
            $remainingSeconds = max(1, ($challenge['expires_at'] ?? now()->timestamp) - now()->timestamp);
            Cache::put($key, $challenge, $remainingSeconds);

            return response()->json(['message' => 'The reset code is incorrect.'], 422);
        }

        Cache::forget($key);
        DB::transaction(function () use ($user, $validated): void {
            $user->forceFill([
                'password' => $validated['password'],
                'remember_token' => Str::random(60),
                // Receiving the emailed code proves ownership of this email.
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            DB::table('mobile_api_tokens')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });
        event(new PasswordReset($user));

        return response()->json([
            'message' => 'Your password has been reset. You can now log in with your new password.',
        ]);
    }

    private function challengeKey(string $challenge): string
    {
        return 'mobile-password-reset-challenge:'.hash('sha256', $challenge);
    }
}
