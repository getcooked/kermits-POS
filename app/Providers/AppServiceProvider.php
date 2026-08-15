<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(
            ['landing', 'shop.index', 'customer.history', 'reservations.create'],
            fn ($view) => $view->with(
                'appDownloadAvailable',
                file_exists(storage_path('app/releases/kermits.apk')),
            ),
        );
    }
}
