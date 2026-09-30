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
            'type' => ['required', 'in:stock_in,stock_out'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
