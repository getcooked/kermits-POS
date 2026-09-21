<?php

namespace App\Providers;

use App\Contracts\FcmMessageSender;
use App\Models\Order;
use App\Models\Reservation;
use App\Observers\OrderObserver;
use App\Observers\ReservationObserver;
use App\Services\GoogleFcmMessageSender;
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
        $this->app->singleton(FcmMessageSender::class, GoogleFcmMessageSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Reservation::observe(ReservationObserver::class);
        Order::observe(OrderObserver::class);

        Password::defaults(fn (): Password => Password::min(8)
            ->max(23)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols());

        View::composer(
            ['landing', 'shop.index', 'customer.history', 'customer.notifications', 'customer.profile', 'customer.settings', 'reservations.create'],
            function ($view): void {
                $releasePath = config('mobile.release_path');
                $appDownloadAvailable = config('mobile.download_enabled')
                    && is_file($releasePath);

                $view->with([
                    'appDownloadAvailable' => $appDownloadAvailable,
                    'appDownloadUrl' => $appDownloadAvailable
                        ? route('app.download', ['v' => filemtime($releasePath)])
                        : null,
                ]);
            },
        );

        View::composer('shop.index', function ($view): void {
            $view->with(
                'customerOrderDecisionKeys',
                auth()->user()->purchases()
                    ->whereIn('payment_status', ['paid', 'rejected'])
                    ->get(['id', 'payment_status'])
                    ->map(fn (Order $order): string => $order->id.':'.$order->payment_status)
                    ->values(),
            );
        });
    }
}
