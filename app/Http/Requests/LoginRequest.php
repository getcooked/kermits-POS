<?php

namespace App\Http\Requests;

use App\Rules\Recaptcha;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:160'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'g-recaptcha-response' => Recaptcha::rules($this),
        ];
    }

    public function messages(): array
    {
        return Recaptcha::messages();
    }
}
