<?php

namespace App\Http\Requests;

use App\Rules\UniqueGcashReference;

class CashierCheckoutRequest extends OrderRequest
{
    public function rules(): array
    {
        return [
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'payment_method' => ['required', 'in:cash,gcash,paymongo'],
            'cash_received' => ['nullable', 'required_if:payment_method,cash', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payment_reference' => ['bail', 'nullable', 'required_if:payment_method,gcash', 'digits:13', new UniqueGcashReference],
        ];
    }

    public function cashReceivedCents(): ?int
    {
        return $this->validated('payment_method') === 'cash'
            ? (int) round((float) $this->validated('cash_received') * 100)
            : null;
    }
}
