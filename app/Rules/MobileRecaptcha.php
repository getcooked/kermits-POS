<?php

namespace App\Rules;

use Illuminate\Http\Request;

class MobileRecaptcha
{
    public static function rules(Request $request): array
    {
        return Recaptcha::rules($request);
    }

    public static function messages(): array
    {
        return [
            'recaptcha_token.required' => 'Please complete the reCAPTCHA checkbox.',
            'recaptcha_token.string' => 'Please complete reCAPTCHA again.',
            'recaptcha_token.max' => 'Please complete reCAPTCHA again.',
        ];
    }
}
