<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\PreventBackHistory;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(PreventBackHistory::class);
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            return $user ? $user->homeRoute() : route('home');
        });
        $middleware->alias(['role' => RoleMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
