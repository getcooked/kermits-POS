<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'category' => Str::squish((string) $this->input('category')),
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
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
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
