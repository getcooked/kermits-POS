<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AuthenticateMobileToken;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RecordActivity;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddSecurityHeaders::class);
        $middleware->append(PreventBackHistory::class);
        $middleware->appendToGroup('web', RecordActivity::class);
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            return $user ? $user->homeRoute() : route('home');
        });
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'mobile.auth' => AuthenticateMobileToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
