<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:stock_in,stock_out,adjustment'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
