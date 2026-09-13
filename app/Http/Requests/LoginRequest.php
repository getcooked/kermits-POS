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
            'g-recaptcha-response' => config('services.recaptcha.enabled')
                ? ['bail', 'required', 'string', 'max:4096', new Recaptcha($this->getHost())]
                : ['exclude'],
        ];
    }

    public function messages(): array
    {
        return [
            'g-recaptcha-response.required' => 'Please complete the reCAPTCHA checkbox.',
            'g-recaptcha-response.string' => 'Please complete reCAPTCHA again.',
            'g-recaptcha-response.max' => 'Please complete reCAPTCHA again.',
        ];
    }
}
