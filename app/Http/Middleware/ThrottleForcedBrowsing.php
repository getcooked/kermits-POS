<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks clients that probe URLs they are not allowed to see, such as
 * scanners guessing admin pages or users walking through other people's IDs.
 */
class ThrottleForcedBrowsing
{
    public const MAX_FAILURES = 30;

    public const WINDOW_SECONDS = 60;

    public const LOCKOUT_SECONDS = 600;

    private const COUNTED_STATUSES = [403, 404, 405];

    // Missing menu photos legitimately 404 many times on a single page load.
    private const IGNORED_ROUTES = ['products.image', 'public.media'];

    public function handle(Request $request, Closure $next): Response
    {
        $lockoutKey = $this->key($request, 'lockout');

        if (RateLimiter::tooManyAttempts($lockoutKey, 1)) {
            throw new ThrottleRequestsException(
                'Too many invalid requests. Please try again later.',
                headers: ['Retry-After' => RateLimiter::availableIn($lockoutKey)],
            );
        }

        $response = $next($request);

        if ($this->isProbe($request, $response)) {
            $failuresKey = $this->key($request, 'failures');

            if (RateLimiter::hit($failuresKey, self::WINDOW_SECONDS) >= self::MAX_FAILURES) {
                RateLimiter::hit($lockoutKey, self::LOCKOUT_SECONDS);
                RateLimiter::clear($failuresKey);
            }
        }

        return $response;
    }

    private function isProbe(Request $request, Response $response): bool
    {
        return in_array($response->getStatusCode(), self::COUNTED_STATUSES, true)
            && ! $request->routeIs(...self::IGNORED_ROUTES);
    }

    private function key(Request $request, string $type): string
    {
        return "forced-browsing:{$type}:".sha1((string) $request->ip());
    }
}
