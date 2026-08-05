<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_SUPER_ADMIN) === true;
    }

    public function rules(): array
    {
        return ['gcash_qr' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']];
    }
}
