<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'payment_method' => ['required', 'in:cash,gcash'],
            'payment_reference' => ['nullable', 'required_if:payment_method,gcash', 'digits:13'],
        ];
    }

    public function selectedQuantities(): array
    {
        return collect($this->validated('quantities'))
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->all();
    }
}
