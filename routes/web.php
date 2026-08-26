<?php

use App\Http\Controllers\AdminAccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashierAccountController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerHistoryController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentSettingsController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SuperAdminSecurityController;
use App\Http\Controllers\WebSeederController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::view('/app', 'mobile.app')->name('mobile.app');
Route::get('/download-app', [LandingController::class, 'downloadApp'])->name('app.download');
Route::get('/menu-images/{product}', ProductImageController::class)->name('products.image');
Route::get('/seeder', [WebSeederController::class, 'show'])->name('web-seeder.show');
Route::post('/seeder', [WebSeederController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('web-seeder.store');

Route::middleware('guest')->controller(AuthController::class)->group(function (): void {
    Route::get('/login', 'create')->name('login');
    Route::post('/login', 'store')->middleware('throttle:5,1')->name('login.store');
    Route::get('/register', 'register')->name('register');
    Route::post('/register/email', 'sendRegistrationCode')->middleware('throttle:3,1')->name('register.email');
    Route::post('/register/email/verify', 'verifyRegistrationCode')->middleware('throttle:6,1')->name('register.email.verify');
    Route::post('/register', 'storeRegistration')->middleware('throttle:3,1')->name('register.store');
});

Route::middleware('guest')->controller(PasswordResetController::class)->group(function (): void {
    Route::get('/forgot-password', 'request')->name('password.request');
    Route::post('/forgot-password', 'email')
        ->middleware('throttle:3,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', 'reset')->name('password.reset');
    Route::post('/reset-password', 'update')
        ->middleware('throttle:5,1')
        ->name('password.update');
    Route::get('/admin/forgot-password', 'requestSuperAdmin')
        ->name('superadmin.password.request');
    Route::post('/admin/forgot-password', 'emailSuperAdmin')
        ->middleware('throttle:3,1')
        ->name('superadmin.password.email');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/reservations/{reservation}/payment-proof', [ReservationController::class, 'proof'])
        ->middleware('role:customer,super_admin')
        ->name('reservations.payment-proof');
    Route::get('/reservations/{reservation}/details', [ReservationController::class, 'show'])
        ->middleware('role:customer,super_admin')
        ->name('reservations.show');
    Route::get('/reservations/{reservation}/receipt', [ReservationController::class, 'receipt'])
        ->middleware('role:customer,super_admin')
        ->name('reservations.receipt');

    Route::middleware('role:customer')->group(function (): void {
        Route::get('/shop', [CustomerOrderController::class, 'index'])->name('shop');
        Route::post('/shop/orders', [CustomerOrderController::class, 'store'])->name('shop.orders.store');
        Route::get('/shop/orders/{order}', [CustomerOrderController::class, 'show'])->name('shop.orders.show');
        Route::get('/history', [CustomerHistoryController::class, 'index'])->name('customer.history');

        Route::controller(ReservationController::class)
            ->group(function (): void {
                Route::get('/book', 'create')->name('reservations.create');
                Route::post('/book', 'store')
                    ->middleware('throttle:10,1')
                    ->name('reservations.store');
                Route::get('/book/success/{reference}', 'success')
                    ->middleware('signed')
                    ->name('reservations.success');
            });
    });

    Route::middleware('role:super_admin')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}/history', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/{product}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->name('reservations.status');
        Route::get('/crud', [ProductController::class, 'index'])->name('crud.index');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    });

    Route::middleware('role:super_admin')->group(function (): void {
        Route::get('/staff/security', [SuperAdminSecurityController::class, 'edit'])
            ->name('superadmin.security.edit');
        Route::put('/staff/security/password', [SuperAdminSecurityController::class, 'updatePassword'])
            ->middleware('throttle:5,1')
            ->name('superadmin.security.password.update');
        Route::get('/staff/admins', [AdminAccountController::class, 'index'])->name('admins.index');
        Route::put('/staff/admins/{admin}/password', [AdminAccountController::class, 'updatePassword'])
            ->middleware('throttle:5,1')
            ->name('admins.password.update');
        Route::get('/staff/cashiers', [CashierAccountController::class, 'index'])->name('cashiers.index');
        Route::post('/staff/cashiers', [CashierAccountController::class, 'store'])->name('cashiers.store');
        Route::put('/staff/cashiers/{cashier}', [CashierAccountController::class, 'update'])->name('cashiers.update');
        Route::delete('/staff/cashiers/{cashier}', [CashierAccountController::class, 'destroy'])->name('cashiers.destroy');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::get('/settings/payment', [PaymentSettingsController::class, 'edit'])->name('settings.payment.edit');
        Route::put('/settings/payment', [PaymentSettingsController::class, 'update'])->name('settings.payment.update');
    });

    Route::middleware('role:super_admin,cashier')->group(function (): void {
        Route::get('/cashier', [CashierController::class, 'index'])->name('cashier');
        Route::post('/cashier/checkout', [CashierController::class, 'checkout'])->name('cashier.checkout');
        Route::get('/cashier/customer-orders', [CashierController::class, 'customerOrders'])
            ->middleware('role:cashier')
            ->name('cashier.orders.index');
        Route::get('/cashier/customer-orders/{order}/review', [CashierController::class, 'reviewCustomerOrder'])
            ->middleware('role:cashier')
            ->name('cashier.orders.review');
        Route::put('/cashier/customer-orders/{order}', [CashierController::class, 'updateCustomerOrder'])
            ->middleware(['role:cashier', 'throttle:30,1'])
            ->name('cashier.orders.update');
        Route::patch('/cashier/orders/{order}/confirm-payment', [CashierController::class, 'confirmCustomerPayment'])
            ->middleware(['role:cashier', 'throttle:20,1'])
            ->name('cashier.orders.confirm-payment');
    });

    Route::get('/receipts/{order}', [ReportController::class, 'receipt'])
        ->middleware('role:super_admin,cashier,customer')
        ->name('receipts.show');
});
