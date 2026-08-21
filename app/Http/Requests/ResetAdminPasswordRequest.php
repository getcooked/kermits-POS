<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetAdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_SUPER_ADMIN) === true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }
}
