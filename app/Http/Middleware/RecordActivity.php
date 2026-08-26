<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $actorBeforeRequest = $request->user();
        $response = $next($request);
        $actor = $actorBeforeRequest ?? $request->user();

        if (! $actor instanceof User || ! $actor->hasRole(
            User::ROLE_SUPER_ADMIN,
            User::ROLE_ADMIN,
            User::ROLE_CASHIER,
        )) {
            return $response;
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        $action = match ($routeName) {
            'login.store' => 'auth.login',
            'logout' => 'auth.logout',
            default => $routeName ?: 'request.'.strtolower($request->method()),
        };
        $description = match ($routeName) {
            'login.store' => 'Signed in',
            'logout' => 'Signed out',
            default => 'Submitted '.$this->routeLabel($routeName, $request->path()),
        };
        $ipAddress = $request->ip();
        $userAgent = trim((string) $request->userAgent());

        try {
            ActivityLog::query()->create([
                'user_id' => $actor->getKey(),
                'actor_name' => Str::limit($actor->name, 255, ''),
                'actor_role' => Str::limit($actor->role, 32, ''),
                'action' => Str::limit($action, 255, ''),
                'description' => Str::limit($description, 255, ''),
                'route_name' => $routeName ? Str::limit($routeName, 255, '') : null,
                'method' => Str::limit($request->method(), 10, ''),
                'path' => Str::limit('/'.ltrim($request->path(), '/'), 2048, ''),
                'status_code' => $response->getStatusCode(),
                'ip_address' => is_string($ipAddress) ? Str::limit($ipAddress, 45, '') : null,
                'user_agent' => $userAgent !== '' ? Str::limit($userAgent, 1000, '') : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function routeLabel(?string $routeName, string $path): string
    {
        return Str::of($routeName ?: $path)
            ->replace(['.', '-', '/'], ' ')
            ->squish()
            ->headline()
            ->toString();
    }
}
