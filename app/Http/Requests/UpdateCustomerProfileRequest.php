<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_CUSTOMER) === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($this->user()),
            ],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'birthday' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'sex' => ['required', 'in:male,female'],
            'address' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'The username may only contain letters, numbers, dots, underscores, and hyphens.',
            'phone.regex' => 'Enter an 11-digit Philippine mobile number starting with 09.',
        ];
    }
}
