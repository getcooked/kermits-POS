<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:160'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);
        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::query()->where($field, $validated['login'])->first();
        if (! $user || ! $user->hasRole(User::ROLE_CUSTOMER) || ! $user->email_verified_at || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'The username/email or password is incorrect.'], 422);
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
}
