<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Views read this nonce through Vite::cspNonce(), so it must exist before rendering.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->remove('X-Powered-By');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(), usb=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request, $response, $nonce));

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request, Response $response, string $nonce): string
    {
        // Laravel's debug exception page ships inline scripts without a nonce.
        $scriptSource = config('app.debug') && ($response->exception ?? null)
            ? "'unsafe-inline'"
            : "'nonce-{$nonce}'";

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "connect-src 'self' https://www.google.com/recaptcha/",
            "frame-src 'self' https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/",
            "font-src 'self' data:",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "img-src 'self' data: https:",
            "media-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
            "object-src 'none'",
            "script-src 'self' {$scriptSource} https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/",
            "script-src-attr 'none'",
            "style-src 'self' 'unsafe-inline'",
        ];

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
