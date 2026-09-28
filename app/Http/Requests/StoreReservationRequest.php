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
            'reservation_at' => self::scheduleRules($this->input('type')),
            'guests' => ['nullable', 'required_if:type,exclusive', 'integer', 'min:1', 'max:300'],
            'food_request' => ['nullable', 'string', 'max:2000'],
            'menu_items' => ['nullable', 'array'],
            'menu_items.*' => ['nullable', 'integer', 'min:0', 'max:22'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => self::paymentMethodRules($this->input('type')),
            'payment_plan' => ['exclude_unless:type,exclusive', 'nullable', 'in:downpayment,full'],
            'payment_reference' => ['nullable', 'required_if:payment_method,gcash', 'digits:13'],
            'payment_proof' => ['nullable', 'required_if:payment_method,gcash', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * A table booking picks an arrival time; the Exclusive Venue takes the whole chosen day.
     */
    public static function scheduleRules(?string $type): array
    {
        return $type === 'exclusive'
            ? ['required', 'bail', 'date']
            : ['required', 'bail', 'date', 'after:now', new ReservationHours];
    }

    /**
     * The Exclusive Venue must be at least partly paid online before it is reserved.
     */
    public static function paymentMethodRules(?string $type): array
    {
        return $type === 'exclusive'
            ? ['required', Rule::in(['gcash', 'paymongo'])]
            : ['required', 'in:cash,gcash,paymongo'];
    }

    public static function paymentMethodMessages(): array
    {
        $percent = app(ReservationPricing::class)->downpaymentPercent();

        return [
            'payment_method.in' => "The Exclusive Venue needs at least a {$percent}% downpayment by GCash or PayMongo before it can be reserved.",
        ];
    }

    public function messages(): array
    {
        return self::paymentMethodMessages();
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('payment_method') === 'paymongo' && ! PayMongoCheckout::enabled()) {
                    $validator->errors()->add('payment_method', 'PayMongo checkout is not available.');
                }

                $schedules = app(ReservationSchedule::class);
                if ($this->input('type') === 'exclusive' && ! $validator->errors()->has('reservation_at')
                    && ! $schedules->exclusiveDayBookable($this->input('reservation_at'))) {
                    $validator->errors()->add('reservation_at', $schedules->exclusiveLeadMessage());
                }

                $tableId = $this->input('type') === 'table' && $this->filled('dining_table_id') ? (int) $this->input('dining_table_id') : null;
                if (! $validator->errors()->hasAny(['reservation_at', 'type', 'table_size', 'guests', 'dining_table_id'])
                    && ! $schedules->isAvailable($this->input('reservation_at'), $this->input('type', 'table'), (int) ($this->input('table_size') ?: $this->input('guests', 1)), $tableId)) {
                    $validator->errors()->add(
                        $tableId !== null ? 'dining_table_id' : 'reservation_at',
                        match (true) {
                            $tableId !== null => 'The table you chose is not free at this time. Choose another time, another table, or any available table.',
                            $this->input('type') === 'exclusive' => 'Kermit\'s is already booked on this day. Please choose another date for the Exclusive Venue.',
                            default => 'This reservation time is no longer available. Please choose another schedule.',
                        },
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

    public function paymentPlan(): string
    {
        return $this->validated('payment_plan') ?? 'downpayment';
    }

    public function selectedMenuItems(): array
    {
        return collect($this->validated('menu_items', []))
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->all();
    }
}
