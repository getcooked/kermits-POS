<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::query()->orderBy('category_order')->orderBy('category')->orderBy('name')->get(),
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

        if ($oldImage && $oldImage !== $product->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return back()->with('status', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if (OrderItem::query()->whereBelongsTo($product)->exists() || $product->reservationItems()->exists()) {
            $product->update(['active' => false]);

            return back()->with('status', 'Products with sales history are hidden instead of deleted.');
        }

        $image = $product->image_path;
        $product->delete();

        if ($image) {
            Storage::disk('public')->delete($image);
        }

        return back()->with('status', 'Product deleted.');
    }
}
