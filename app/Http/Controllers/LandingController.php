<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LandingController extends Controller
{
    public function index(): View
    {
        $bestSellers = Product::query()
            ->available()
            ->withSum([
                'orderItems as sold_units' => fn ($query) => $query->whereHas(
                    'order',
                    fn ($order) => $order->where('payment_status', 'paid'),
                ),
            ], 'quantity')
            ->orderByDesc('sold_units')
            ->limit(6)
            ->get();

        if ((int) $bestSellers->sum('sold_units') === 0) {
            $favorites = [
                "Kermit's Classic Burger",
                'Aligue Pasta',
                'Baked Spaghetti',
                'Chicken Fun Bites',
                'Beef with Onions',
                'Mango Overload · Slice',
            ];

            $bestSellers = Product::query()
                ->available()
                ->whereIn('name', $favorites)
                ->get()
                ->sortBy(fn (Product $product) => array_search($product->name, $favorites, true))
                ->values();
        }

        return view('landing', ['products' => $bestSellers]);
    }

    public function downloadApp(): BinaryFileResponse
    {
        abort_unless(
            config('mobile.download_enabled')
                && file_exists($this->appReleasePath()),
            404,
        );

        return response()->download(
            $this->appReleasePath(),
            'Kermits-Restaurant.apk',
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }

    private function appReleasePath(): string
    {
        return storage_path('app/releases/kermits.apk');
    }
}
