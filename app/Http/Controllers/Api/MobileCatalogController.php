<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

class MobileCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::query()->available()->where('stock', '>', 0)->menuOrder()->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id, 'name' => $product->name, 'category' => $product->category,
                'description' => $product->description, 'price' => (float) $product->price,
                'stock' => $product->stock,
                'image_url' => $product->imageUrl(),
            ]);
        $qrPath = SystemSetting::get('gcash_qr_path');

        return response()->json(['data' => [
            'products' => $products,
            'gcash_qr_url' => $qrPath ? asset('storage/'.$qrPath) : null,
        ]]);
    }
}
