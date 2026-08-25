<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Password::defaults(fn (): Password => Password::min(12)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols());

        View::composer(
            ['landing', 'shop.index', 'customer.history', 'reservations.create'],
            fn ($view) => $view->with(
                'appDownloadAvailable',
                config('mobile.download_enabled')
                    && file_exists(storage_path('app/releases/kermits.apk')),
            ),
        );
    }
}
