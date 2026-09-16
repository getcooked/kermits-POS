<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateCustomerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_SUPER_ADMIN) === true;
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($customer)],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($customer)],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'birthday' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'sex' => ['required', 'in:male,female'],
            'address' => ['required', 'string', 'max:500'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
