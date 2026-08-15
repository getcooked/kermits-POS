<?php

namespace App\Http\Middleware;

use App\Models\MobileApiToken;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        if (! $plainToken) {
            return $this->unauthorized();
        }

        $token = MobileApiToken::query()->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())->first();

        if (! $token?->user?->hasRole(User::ROLE_CUSTOMER)) {
            return $this->unauthorized();
        }

        $request->setUserResolver(fn (): User => $token->user);
        $request->attributes->set('mobile_token', $token);
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->update(['last_used_at' => now()]);
        }

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
