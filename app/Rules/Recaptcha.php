<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Recaptcha implements ValidationRule
{
    public function __construct(private readonly string $hostname) {}

    public static function rules(Request $request): array
    {
        return config('services.recaptcha.enabled')
            ? ['bail', 'required', 'string', 'max:4096', new self($request->getHost())]
            : ['exclude'];
    }

    public static function messages(): array
    {
        return [
            'g-recaptcha-response.required' => 'Please complete the reCAPTCHA checkbox.',
            'g-recaptcha-response.string' => 'Please complete reCAPTCHA again.',
            'g-recaptcha-response.max' => 'Please complete reCAPTCHA again.',
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.recaptcha.secret_key');

        if (! $secret || ! config('services.recaptcha.site_key')) {
            $fail('reCAPTCHA verification is unavailable. Please try again later.');

            return;
        }

        try {
            $response = Http::asForm()->connectTimeout(3)->timeout(8)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $value,
                ]);
        } catch (ConnectionException) {
            $fail('Unable to contact reCAPTCHA. Please try again.');

            return;
        }

        if (! $response->successful()) {
            $fail('Unable to contact reCAPTCHA. Please try again.');

            return;
        }

        if ($response->json('success') !== true
            || strcasecmp((string) $response->json('hostname'), $this->hostname) !== 0) {
            $fail('reCAPTCHA verification failed or expired. Please complete it again.');
        }
    }
}
