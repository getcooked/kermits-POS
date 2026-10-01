<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\ProductImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = preg_replace('/\s+/', ' ', trim((string) $request->query('search', ''))) ?? '';
        $terms = array_slice(preg_split('/\s+/', $search, flags: PREG_SPLIT_NO_EMPTY) ?: [], 0, 10);

        return view('products.index', [
            'products' => Product::query()
                ->when($terms !== [], function ($query) use ($terms): void {
                    foreach ($terms as $term) {
                        $query->where(function ($query) use ($term): void {
                            $query->where('name', 'like', '%'.$term.'%')
                                ->orWhere('category', 'like', '%'.$term.'%');
                        });
                    }
                })
                ->menuOrder()
                ->get(),
            'search' => $search,
            'categories' => Product::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'categoryCounts' => Product::query()
                ->whereNotNull('category')
                ->selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->pluck('total', 'category'),
            'searchProducts' => Product::query()
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    public function store(ProductRequest $request, ProductImageProcessor $images): RedirectResponse
    {
        $data = $request->productData();
        $data['category_order'] = $this->categoryOrder($data['category']);
        $newImage = null;

        if ($request->hasFile('image')) {
            $newImage = $images->store($request->file('image'), framed: $request->boolean('image_framed'));
            $data['image_path'] = $newImage;
        }

        try {
            Product::query()->create($data);
        } catch (Throwable $exception) {
            if ($newImage) {
                Storage::disk('public')->delete($newImage);
            }

            throw $exception;
        }

        return redirect()->route('products.index')->with('status', 'Product added successfully.');
    }

    public function update(ProductRequest $request, Product $product, ProductImageProcessor $images): RedirectResponse
    {
        $data = $request->productData();
        $data['category_order'] = $this->categoryOrder($data['category'], $product);
        $oldImage = $product->image_path;
        $newImage = null;

        if ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            $newImage = $images->store($request->file('image'), framed: $request->boolean('image_framed'));
            $data['image_path'] = $newImage;
        }

        try {
            $product->update($data);
        } catch (Throwable $exception) {
            if ($newImage) {
                Storage::disk('public')->delete($newImage);
            }

            throw $exception;
        }

        if ($this->isStoredImage($oldImage) && $oldImage !== $product->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('products.index')->with('status', 'Product updated and moved to '.$product->category.'.');
    }

    public function updateVisibility(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);
        $product->update(['active' => $request->boolean('active')]);
        $message = $product->name.($product->active ? ' is now enabled.' : ' is now disabled.');

        return $this->visibilityResponse($request, $message, [$product->getKey()], $product->active);
    }

    public function bulkVisibility(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'active' => ['required', 'boolean'],
        ]);
        $active = $request->boolean('active');
        $ids = array_map('intval', $validated['ids']);
        $updated = Product::query()->whereKey($ids)->update(['active' => $active]);
        $message = $updated.' '.Str::plural('product', $updated).($active ? ' enabled.' : ' disabled.');

        return $this->visibilityResponse($request, $message, $ids, $active);
    }

    private function visibilityResponse(Request $request, string $message, array $ids, bool $active): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('status', $message);
        }

        return response()->json([
            'message' => $message,
            'ids' => $ids,
            'active' => $active,
            'low_stock_count' => Product::query()->available()->lowStock()->count(),
        ]);
    }

    /**
     * Categories are just a name on each product, so deleting one moves its products
     * to another category first; nothing is lost and order history is untouched.
     */
    public function destroyCategory(Request $request): RedirectResponse
    {
        $request->merge(['move_to' => Str::title(Str::squish((string) $request->input('move_to')))]);
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:80', Rule::exists('products', 'category')],
            'move_to' => ['required', 'string', 'max:80', 'different:category'],
        ], [
            'category.exists' => 'That category no longer exists.',
            'move_to.required' => 'Choose where its products should go.',
            'move_to.different' => 'Choose a different category to move the products to.',
        ]);

        $order = $this->categoryOrder($validated['move_to']);
        $moved = Product::query()
            ->where('category', $validated['category'])
            ->update(['category' => $validated['move_to'], 'category_order' => $order]);

        return redirect()->route('products.index')->with(
            'status',
            "Deleted the {$validated['category']} category and moved {$moved} ".Str::plural('product', $moved)." to {$validated['move_to']}.",
        );
    }

    private function categoryOrder(string $category, ?Product $except = null): int
    {
        $existingOrder = Product::query()
            ->where('category', $category)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->min('category_order');

        if ($existingOrder !== null) {
            return (int) $existingOrder;
        }

        if ($except?->category === $category) {
            return (int) $except->category_order;
        }

        return min(65535, ((int) Product::query()->max('category_order')) + 1);
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
