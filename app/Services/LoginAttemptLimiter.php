<?php

namespace App\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoginAttemptLimiter
{
    public const MAX_ATTEMPTS = 5;

    public const ATTEMPT_WINDOW_SECONDS = 60;

    public const LOCKOUT_SECONDS = 30;

    public function __construct(private readonly RateLimiter $limiter) {}

    public function isLocked(Request $request, string $login): bool
    {
        return $this->limiter->tooManyAttempts($this->lockoutKey($request, $login), 1);
    }

    public function recordFailure(Request $request, string $login): bool
    {
        $failureKey = $this->failureKey($request, $login);
        $attempts = $this->limiter->hit($failureKey, self::ATTEMPT_WINDOW_SECONDS);

        if ($attempts < self::MAX_ATTEMPTS) {
            return false;
        }

        $this->limiter->clear($failureKey);
        $this->limiter->hit($this->lockoutKey($request, $login), self::LOCKOUT_SECONDS);

        return true;
    }

    public function secondsRemaining(Request $request, string $login): int
    {
        return max(1, $this->limiter->availableIn($this->lockoutKey($request, $login)));
    }

    public function clear(Request $request, string $login): void
    {
        $this->limiter->clear($this->failureKey($request, $login));
        $this->limiter->clear($this->lockoutKey($request, $login));
    }

    private function failureKey(Request $request, string $login): string
    {
        return $this->baseKey($request, $login).':failures';
    }

    private function lockoutKey(Request $request, string $login): string
    {
        return $this->baseKey($request, $login).':lockout';
    }

    private function baseKey(Request $request, string $login): string
    {
        return 'login-attempts:'.hash(
            'sha256',
            Str::lower(trim($login)).'|'.(string) $request->ip(),
        );
    }
}
