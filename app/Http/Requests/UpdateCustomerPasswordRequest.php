<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateCustomerPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_CUSTOMER) === true;
    }

    public function rules(): array
    {
        return [
            'verification_code' => ['required', 'digits:6'],
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('verification_code')) {
                return;
            }

            $verification = $this->session()->get('customer_password_verification');
            $codeHash = is_array($verification) ? ($verification['code_hash'] ?? null) : null;
            $valid = is_array($verification)
                && (int) ($verification['user_id'] ?? 0) === (int) $this->user()?->getKey()
                && hash_equals(strtolower((string) ($verification['email'] ?? '')), strtolower((string) $this->user()?->email))
                && (int) ($verification['expires_at'] ?? 0) >= now()->timestamp
                && is_string($codeHash)
                && $codeHash !== ''
                && Hash::check($this->string('verification_code')->toString(), $codeHash);

            if (! $valid) {
                $validator->errors()->add('verification_code', 'The email verification code is invalid or has expired. Request a new code.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'verification_code.required' => 'Enter the verification code sent to your email.',
            'current_password.current_password' => 'The current password is incorrect.',
            'password.different' => 'Choose a new password that is different from your current password.',
        ];
    }
}
