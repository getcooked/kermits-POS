<?php

namespace App\Http\Requests;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SeatTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_SUPER_ADMIN, User::ROLE_CASHIER) === true;
    }

    public function rules(): array
    {
        return [
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'reservation_id.exists' => 'That reservation no longer exists. Refresh the page.',
        ];
    }

    /**
     * The booking being seated, or null for a walk-in party.
     */
    public function reservation(): ?Reservation
    {
        $id = $this->validated('reservation_id');

        return filled($id) ? Reservation::query()->find((int) $id) : null;
    }
}
