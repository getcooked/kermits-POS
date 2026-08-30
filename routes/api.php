<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileCatalogController;
use App\Http\Controllers\Api\MobileOrderController;
use App\Http\Controllers\Api\MobilePasswordResetController;
use App\Http\Controllers\Api\MobilePushInstallationController;
use App\Http\Controllers\Api\MobileRegistrationController;
use App\Http\Controllers\Api\MobileReservationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/login', [MobileAuthController::class, 'login']);
    Route::post('/password/forgot', [MobilePasswordResetController::class, 'store'])->middleware('throttle:3,1');
    Route::post('/register/email', [MobileRegistrationController::class, 'sendCode'])->middleware('throttle:3,1');
    Route::post('/register/email/verify', [MobileRegistrationController::class, 'verifyCode'])->middleware('throttle:6,1');
    Route::post('/register', [MobileRegistrationController::class, 'register'])->middleware('throttle:3,1');
    Route::middleware('mobile.auth')->group(function (): void {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::put('/push-installation', [MobilePushInstallationController::class, 'update'])->middleware('throttle:30,1');
        Route::delete('/push-installation', [MobilePushInstallationController::class, 'destroy'])->middleware('throttle:30,1');
        Route::get('/products', [MobileCatalogController::class, 'index']);
        Route::get('/orders', [MobileOrderController::class, 'index']);
        Route::post('/orders', [MobileOrderController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/orders/{order}', [MobileOrderController::class, 'show']);
        Route::get('/reservations', [MobileReservationController::class, 'index']);
        Route::post('/reservations', [MobileReservationController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/reservations/{reservation}', [MobileReservationController::class, 'show']);
    });
});
