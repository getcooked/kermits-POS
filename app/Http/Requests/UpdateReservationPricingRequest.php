<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\ReservationPricing;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_SUPER_ADMIN) === true;
    }

    public function rules(): array
    {
        $rules = [
            'table_fees' => ['required', 'array'],
        ];

        foreach (ReservationPricing::TABLE_SIZES as $size) {
            $rules['table_fees.'.$size] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'];
        }

        return $rules;
    }
}
