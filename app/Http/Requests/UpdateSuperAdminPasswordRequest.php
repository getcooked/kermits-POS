<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateSuperAdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_SUPER_ADMIN) === true;
    }

    public function rules(): array
    {
        return [
            'verification_code' => ['required', 'digits:6'],
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'different:current_password', 'confirmed', Password::defaults()],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('verification_code')) {
                return;
            }

            $verification = $this->session()->get('super_admin_password_verification');
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
            'password.different' => 'The new password must be different from the current password.',
        ];
    }
}
