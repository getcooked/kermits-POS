<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\ReservationPricing;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTableManagementRequest extends FormRequest
{
    public const MAX_TABLES = 20;

    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_SUPER_ADMIN) === true;
    }

    public function rules(): array
    {
        $maxGuests = app(ReservationPricing::class)->maxGuests();

        return [
            'tables' => ['required', 'array', 'min:1', 'max:'.self::MAX_TABLES],
            'tables.*' => ['required', 'array:guests,fee'],
            'tables.*.guests' => ['required', 'integer', 'min:1', 'max:'.$maxGuests, 'distinct'],
            'tables.*.fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
        ];
    }

    public function messages(): array
    {
        $maxGuests = app(ReservationPricing::class)->maxGuests();

        return [
            'tables.required' => 'Add at least one table.',
            'tables.min' => 'Add at least one table.',
            'tables.max' => 'You can have up to '.self::MAX_TABLES.' tables.',
            'tables.*.guests.required' => 'Enter the guest number for every table.',
            'tables.*.guests.integer' => 'Guest numbers must be whole numbers.',
            'tables.*.guests.min' => 'Each table must seat at least 1 guest.',
            'tables.*.guests.max' => "The largest table in the venue seats {$maxGuests} guests.",
            'tables.*.guests.distinct' => 'Each table must have a different guest number.',
            'tables.*.fee.required' => 'Enter the price for every table.',
            'tables.*.fee.numeric' => 'Table prices must be numbers.',
            'tables.*.fee.decimal' => 'Table prices can have at most 2 decimal places.',
            'tables.*.fee.min' => 'Table prices cannot be negative.',
            'tables.*.fee.max' => 'Table prices cannot exceed ₱999,999.99.',
        ];
    }

    /**
     * @return array<int, float>
     */
    public function tableFees(): array
    {
        return collect($this->validated('tables'))
            ->mapWithKeys(fn (array $table): array => [(int) $table['guests'] => (float) $table['fee']])
            ->all();
    }
}
