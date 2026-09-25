<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\ReservationHours;
use App\Services\ReservationSchedule;
use App\Services\PayMongoCheckout;
use App\Services\ReservationPricing;
use App\Services\TableLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_CUSTOMER) === true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:table,exclusive'],
            'table_size' => ['nullable', 'required_if:type,table', 'integer', Rule::in(app(ReservationPricing::class)->tableSizes())],
            'dining_table_id' => ['exclude_unless:type,table', ...app(TableLayout::class)->requestRules()],
            'customer_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'reservation_at' => ['required', 'bail', 'date', 'after:now', new ReservationHours],
            'guests' => ['nullable', 'required_if:type,exclusive', 'integer', 'min:1', 'max:300'],
            'food_request' => ['nullable', 'string', 'max:2000'],
            'menu_items' => ['nullable', 'array'],
            'menu_items.*' => ['nullable', 'integer', 'min:0', 'max:22'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', 'in:cash,paymongo'],
            'payment_reference' => ['prohibited'],
            'payment_proof' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('payment_method') === 'paymongo' && ! PayMongoCheckout::enabled()) {
                    $validator->errors()->add('payment_method', 'PayMongo checkout is not available.');
                }

                $tableId = $this->input('type') === 'table' && $this->filled('dining_table_id') ? (int) $this->input('dining_table_id') : null;
                if (! $validator->errors()->hasAny(['reservation_at', 'type', 'table_size', 'guests', 'dining_table_id'])
                    && ! app(ReservationSchedule::class)->isAvailable($this->input('reservation_at'), $this->input('type', 'table'), (int) ($this->input('table_size') ?: $this->input('guests', 1)), $tableId)) {
                    $validator->errors()->add(
                        $tableId !== null ? 'dining_table_id' : 'reservation_at',
                        $tableId !== null
                            ? 'The table you chose is not free at this time. Choose another time, another table, or any available table.'
                            : 'This reservation time is no longer available. Please choose another schedule.',
                    );
                }

                if ($this->input('type') !== 'table') {
                    return;
                }

                if ((int) $this->input('guests') > (int) $this->input('table_size')) {
                    $validator->errors()->add('table_size', 'Choose a table with enough seats for every guest.');
                }
            },
        ];
    }

    public function selectedMenuItems(): array
    {
        return collect($this->validated('menu_items', []))
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->all();
    }
}
