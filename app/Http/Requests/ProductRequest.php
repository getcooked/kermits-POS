<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Services\ProductImageProcessor;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'category' => Str::title(Str::squish((string) $this->input('category'))),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
            // Opening stock is set when a product is created; after that, stock only changes in Inventory,
            // where every adjustment is recorded, so edits ignore it.
            'stock' => $this->isMethod('POST')
                ? ['required', 'integer', 'min:0', 'max:'.Product::MAX_STOCK]
                : ['exclude'],
            'active' => ['nullable', 'boolean'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value instanceof UploadedFile && app(ProductImageProcessor::class)->isBrandLogo((string) file_get_contents($value->getRealPath()))) {
                        $fail("That's the Kermit's logo. Please upload a photo of the dish instead.");
                    }
                },
            ],
            'image_framed' => ['nullable', 'boolean'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'stock.max' => 'Stock cannot be more than :max.',
        ];
    }

    public function productData(): array
    {
        return [
            ...$this->safe()->only(['name', 'description', 'price', 'stock']),
            'category' => $this->validated('category') ?: 'Uncategorized',
            'active' => $this->boolean('active'),
        ];
    }
}
