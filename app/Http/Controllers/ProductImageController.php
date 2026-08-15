<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageController extends Controller
{
    public function __invoke(Product $product): StreamedResponse
    {
        abort_unless(
            $product->image_path && Storage::disk('public')->exists($product->image_path),
            404,
        );

        $response = Storage::disk('public')->response($product->image_path);
        $response->setPublic();
        $response->setMaxAge(86400);

        return $response;
    }
}
