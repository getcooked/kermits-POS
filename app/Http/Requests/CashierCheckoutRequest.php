<?php

namespace App\Http\Requests;

class CashierCheckoutRequest extends OrderRequest
{
    public function rules(): array
    {
        return [
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'payment_method' => ['required', 'in:cash,gcash'],
            'cash_received' => ['nullable', 'required_if:payment_method,cash', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payment_reference' => ['nullable', 'required_if:payment_method,gcash', 'digits:13'],
        ];
    }

    public function cashReceivedCents(): ?int
    {
        return $this->validated('payment_method') === 'cash'
            ? (int) round((float) $this->validated('cash_received') * 100)
            : null;
    }
}
