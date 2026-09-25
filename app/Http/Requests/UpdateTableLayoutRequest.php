<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\TableLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTableLayoutRequest extends FormRequest
{
    public const MAX_TABLES = 50;

    public const MAX_SEATS = 50;

    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_SUPER_ADMIN) === true;
    }

    public function rules(): array
    {
        return [
            'dining_tables' => ['required', 'array', 'min:1', 'max:'.self::MAX_TABLES],
            'dining_tables.*' => ['required', 'array:id,number,seats,active'],
            'dining_tables.*.id' => ['nullable', 'integer', 'distinct', 'exists:dining_tables,id'],
            'dining_tables.*.number' => ['required', 'integer', 'min:1', 'max:999', 'distinct'],
            'dining_tables.*.seats' => ['required', 'integer', 'min:1', 'max:'.self::MAX_SEATS],
            'dining_tables.*.active' => ['nullable', 'boolean'],
            'turnover_minutes' => ['required', 'integer', 'min:0', 'max:'.TableLayout::MAX_TURNOVER_MINUTES],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (collect($this->tables())->where('active', true)->isEmpty()) {
                $validator->errors()->add('dining_tables', 'Keep at least one table bookable.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'dining_tables.required' => 'Add at least one table.',
            'dining_tables.min' => 'Add at least one table.',
            'dining_tables.max' => 'You can have up to '.self::MAX_TABLES.' tables.',
            'dining_tables.*.number.required' => 'Enter a number for every table.',
            'dining_tables.*.number.integer' => 'Table numbers must be whole numbers.',
            'dining_tables.*.number.min' => 'Table numbers start at 1.',
            'dining_tables.*.number.max' => 'Table numbers can go up to 999.',
            'dining_tables.*.number.distinct' => 'Each table needs a different number.',
            'dining_tables.*.seats.required' => 'Enter the number of seats for every table.',
            'dining_tables.*.seats.integer' => 'Seats must be whole numbers.',
            'dining_tables.*.seats.min' => 'Each table needs at least 1 seat.',
            'dining_tables.*.seats.max' => 'A table can have up to '.self::MAX_SEATS.' seats.',
            'turnover_minutes.required' => 'Enter the cleanup time between bookings (0 for none).',
            'turnover_minutes.integer' => 'Cleanup time must be a whole number of minutes.',
            'turnover_minutes.min' => 'Cleanup time cannot be negative.',
            'turnover_minutes.max' => 'Cleanup time can be at most '.TableLayout::MAX_TURNOVER_MINUTES.' minutes.',
        ];
    }

    /**
     * @return list<array{id: int|null, number: int, seats: int, active: bool}>
     */
    public function tables(): array
    {
        return collect($this->validated('dining_tables'))
            ->map(fn (array $table): array => [
                'id' => filled($table['id'] ?? null) ? (int) $table['id'] : null,
                'number' => (int) $table['number'],
                'seats' => (int) $table['seats'],
                'active' => filter_var($table['active'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();
    }
}
