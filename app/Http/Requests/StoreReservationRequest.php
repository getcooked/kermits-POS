<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_CUSTOMER) === true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['required', 'in:table,exclusive'],
            'table_size' => ['nullable', 'required_if:type,table', 'integer', 'in:1,2,4,8,12'],
            'customer_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'reservation_at' => ['required', 'date', 'after:now'],
            'guests' => ['nullable', 'required_if:type,exclusive', 'integer', 'min:1', 'max:300'],
            'food_request' => ['nullable', 'string', 'max:2000'],
            'menu_items' => ['nullable', 'array'],
            'menu_items.*' => ['nullable', 'integer', 'min:0', 'max:22'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', 'in:cash,gcash'],
            'payment_reference' => ['nullable', 'required_if:payment_method,gcash', 'digits:13'],
            'payment_proof' => ['nullable', 'required_if:payment_method,gcash', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') !== 'table') {
                    return;
                }

                if ((int) $this->input('guests') > (int) $this->input('table_size')) {
                    $validator->errors()->add('table_size', 'Choose a table with enough seats for every guest.');
                }
            },
        ];
    }

    public function selectedMenuItems(): array
    {
        return collect($this->validated('menu_items', []))
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->all();
    }
}
