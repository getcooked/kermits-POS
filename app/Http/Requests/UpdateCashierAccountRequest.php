<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateCashierAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_SUPER_ADMIN) === true;
    }

    public function rules(): array
    {
        $cashier = $this->route('cashier');

        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($cashier)],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($cashier)],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }
}
