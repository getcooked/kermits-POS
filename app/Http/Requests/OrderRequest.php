<?php

namespace App\Http\Requests;

use App\Services\ReservationSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'table_size' => ['required', 'integer', 'in:1,2,4,8,12'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'reservation_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'food_request' => ['prohibited'],
            'menu_items' => ['prohibited'],
            'payment_method' => ['required', 'in:cash,gcash'],
            'payment_reference' => ['nullable', 'required_if:payment_method,gcash', 'digits:13'],
            'payment_proof' => ['nullable', 'required_if:payment_method,gcash', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('reservation_at')
                && ! $validator->errors()->has('reservation_at')
                && ! app(ReservationSchedule::class)->isAvailable($this->input('reservation_at'))) {
                $validator->errors()->add('reservation_at', 'This reservation time is no longer available. Please choose another schedule.');
            }
        }];
    }

    public function selectedQuantities(): array
    {
        return collect($this->validated('quantities'))
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->all();
    }
}
