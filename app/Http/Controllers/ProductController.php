<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::query()->menuOrder()->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->productData();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        Product::query()->create($data);

        return back()->with('status', 'Product added successfully.');
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->productData();
        $oldImage = $product->image_path;

        if ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        if ($this->isStoredImage($oldImage) && $oldImage !== $product->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return back()->with('status', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if (
            OrderItem::query()->whereBelongsTo($product)->exists()
            || $product->reservationItems()->exists()
            || StockMovement::query()->whereBelongsTo($product)->exists()
        ) {
            return back()->withErrors('This product cannot be deleted because it already has order, reservation, or inventory history.');
        }

        $image = $product->image_path;
        $product->delete();

        if ($this->isStoredImage($image)) {
            Storage::disk('public')->delete($image);
        }

        return back()->with('status', 'Product deleted.');
    }

    private function isStoredImage(?string $image): bool
    {
        return filled($image) && ! filter_var($image, FILTER_VALIDATE_URL);
    }
}
