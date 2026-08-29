<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileApiToken;
use App\Models\User;
use App\Services\LoginAttemptLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MobileAuthController extends Controller
{
    public function login(Request $request, LoginAttemptLimiter $loginAttempts): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:160'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);
        $login = trim($validated['login']);

        if ($loginAttempts->isLocked($request, $login)) {
            return $this->lockoutResponse($request, $loginAttempts, $login);
        }

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::query()->where($field, $login)->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            if ($loginAttempts->recordFailure($request, $login)) {
                return $this->lockoutResponse($request, $loginAttempts, $login);
            }

            return response()->json(['message' => 'The username/email or password is incorrect.'], 422);
        }

        $loginAttempts->clear($request, $login);
        if (! $user->hasRole(User::ROLE_CUSTOMER)) {
            return response()->json(['message' => 'This mobile app is for customer accounts. Please use the website for staff access.'], 422);
        }
        if (! $user->email_verified_at) {
            return response()->json(['message' => 'Please verify your Gmail address before signing in.'], 422);
        }

        MobileApiToken::query()->where('expires_at', '<=', now())->delete();
        $plainToken = bin2hex(random_bytes(32));
        MobileApiToken::query()->create([
            'user_id' => $user->id,
            'name' => $validated['device_name'] ?? 'Android device',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(30),
        ]);
        $oldTokenIds = MobileApiToken::query()
            ->whereBelongsTo($user)
            ->latest()
            ->pluck('id')
            ->slice(5);
        MobileApiToken::query()->whereKey($oldTokenIds)->delete();

        return response()->json(['data' => ['token' => $plainToken, 'user' => $this->userData($user)]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userData($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('mobile_token')?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function userData(User $user): array
    {
        return $user->only(['id', 'name', 'username', 'email', 'phone', 'role']);
    }

    private function lockoutResponse(
        Request $request,
        LoginAttemptLimiter $loginAttempts,
        string $login,
    ): JsonResponse {
        $retryAfter = $loginAttempts->secondsRemaining($request, $login);

        return response()->json([
            'message' => "Too many login attempts. Try again in {$retryAfter} seconds.",
            'retry_after' => $retryAfter,
        ], 429)->header('Retry-After', (string) $retryAfter);
    }
}
