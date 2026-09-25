@extends('layouts.app')
@section('title', 'Table Management')
@section('content')
@php
    $optionRows = old('tables', collect($tableFees)->map(fn ($fee, $guests) => ['guests' => $guests, 'fee' => number_format($fee, 2, '.', '')])->values()->all());
    $tablesById = $diningTables->keyBy('id');
    $layoutRows = old('dining_tables', $diningTables->map(fn ($table) => ['id' => $table->id, 'number' => $table->number, 'seats' => $table->seats, 'active' => $table->active])->all());
    $seatSummary = $diningTables->where('active', true)->countBy('seats')->sortKeys();
@endphp
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard table-management">
    <header>
        <p>SUPER ADMIN SETTINGS</p>
        <h1>Table Management</h1>
        <span>Set what customers can book, and the tables in the restaurant that seat them.</span>
    </header>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error table-error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="welcome">
        <h2>Table options</h2>
        <p class="table-help">The party sizes customers choose from when booking, and the reservation price for each.</p>
        <form method="POST" action="{{ route('tables.update') }}" data-ajax-form data-ajax-target=".table-management" data-ajax-loading="Saving..." data-rows-form data-max-rows="{{ $maxTables }}">
            @csrf @method('PUT')
            <div class="table-list-scroll">
                <table class="table-list">
                    <thead><tr><th scope="col">Guest number</th><th scope="col">Price (&#8369;)</th><th scope="col"><span class="visually-hidden">Remove</span></th></tr></thead>
                    <tbody data-rows>
                        @foreach($optionRows as $index => $row)
                            <tr class="editable-row">
                                <td><label class="visually-hidden" for="table_guests_{{ $index }}">Guest number</label><input class="control" id="table_guests_{{ $index }}" name="tables[{{ $index }}][guests]" type="number" min="1" max="{{ $maxGuests }}" step="1" value="{{ $row['guests'] ?? '' }}" required></td>
                                <td><label class="visually-hidden" for="table_fee_{{ $index }}">Price</label><input class="control" id="table_fee_{{ $index }}" name="tables[{{ $index }}][fee]" type="number" min="0" max="999999.99" step="0.01" value="{{ $row['fee'] ?? '' }}" required></td>
                                <td><button class="row-remove" type="button" data-row-remove>Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-row-template>
                <tr class="editable-row">
                    <td><label class="visually-hidden" for="table_guests___INDEX__">Guest number</label><input class="control" id="table_guests___INDEX__" name="tables[__INDEX__][guests]" type="number" min="1" max="{{ $maxGuests }}" step="1" required></td>
                    <td><label class="visually-hidden" for="table_fee___INDEX__">Price</label><input class="control" id="table_fee___INDEX__" name="tables[__INDEX__][fee]" type="number" min="0" max="999999.99" step="0.01" required></td>
                    <td><button class="row-remove" type="button" data-row-remove>Remove</button></td>
                </tr>
            </template>
            <p class="table-help">Guest number is the most guests the option allows, up to {{ $maxGuests }} (the largest bookable table). Changes apply to new bookings only; existing reservations keep their original price.</p>
            <div class="table-actions">
                <button class="row-add" type="button" data-row-add>+ Add option</button>
                <button class="button" type="submit">Save table options</button>
            </div>
        </form>
    </section>

    <section class="welcome">
        <h2>Tables in the restaurant</h2>
        <p class="table-help">Each numbered table and how many people it seats. Customers are seated at the smallest free table that fits, or at the table they request.</p>
        @if($seatSummary->isNotEmpty())
            <p class="seat-summary">@foreach($seatSummary as $seats => $count)<span>{{ $count }} &times; {{ $seats }}-seat</span>@endforeach</p>
        @endif
        <form method="POST" action="{{ route('tables.layout.update') }}" data-ajax-form data-ajax-target=".table-management" data-ajax-loading="Saving..." data-rows-form data-max-rows="{{ $maxDiningTables }}">
            @csrf @method('PUT')
            <div class="table-list-scroll">
                <table class="table-list layout-list">
                    <thead><tr><th scope="col">Table number</th><th scope="col">Seats</th><th scope="col">Bookable</th><th scope="col"><span class="visually-hidden">Remove</span></th></tr></thead>
                    <tbody data-rows>
                        @foreach($layoutRows as $index => $row)
                            @php($inUse = filled($row['id'] ?? null) && ($tablesById->get((int) $row['id'])?->reservations_count ?? 0) > 0)
                            <tr class="editable-row">
                                <td>@if(filled($row['id'] ?? null))<input type="hidden" name="dining_tables[{{ $index }}][id]" value="{{ $row['id'] }}">@endif<label class="visually-hidden" for="dining_table_number_{{ $index }}">Table number</label><input class="control" id="dining_table_number_{{ $index }}" name="dining_tables[{{ $index }}][number]" type="number" min="1" max="999" step="1" value="{{ $row['number'] ?? '' }}" required data-table-number></td>
                                <td><label class="visually-hidden" for="dining_table_seats_{{ $index }}">Seats</label><input class="control" id="dining_table_seats_{{ $index }}" name="dining_tables[{{ $index }}][seats]" type="number" min="1" max="{{ $maxSeats }}" step="1" value="{{ $row['seats'] ?? '' }}" required></td>
                                <td><input type="hidden" name="dining_tables[{{ $index }}][active]" value="0"><label class="bookable"><input type="checkbox" name="dining_tables[{{ $index }}][active]" value="1" @checked(filter_var($row['active'] ?? false, FILTER_VALIDATE_BOOLEAN))><span class="visually-hidden">Bookable</span></label></td>
                                <td>@if($inUse)<button class="row-remove" type="button" disabled title="This table has reservations. Untick Bookable to stop new bookings.">Has bookings</button>@else<button class="row-remove" type="button" data-row-remove>Remove</button>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-row-template>
                <tr class="editable-row">
                    <td><label class="visually-hidden" for="dining_table_number___INDEX__">Table number</label><input class="control" id="dining_table_number___INDEX__" name="dining_tables[__INDEX__][number]" type="number" min="1" max="999" step="1" required data-table-number></td>
                    <td><label class="visually-hidden" for="dining_table_seats___INDEX__">Seats</label><input class="control" id="dining_table_seats___INDEX__" name="dining_tables[__INDEX__][seats]" type="number" min="1" max="{{ $maxSeats }}" step="1" value="4" required></td>
                    <td><input type="hidden" name="dining_tables[__INDEX__][active]" value="0"><label class="bookable"><input type="checkbox" name="dining_tables[__INDEX__][active]" value="1" checked><span class="visually-hidden">Bookable</span></label></td>
                    <td><button class="row-remove" type="button" data-row-remove>Remove</button></td>
                </tr>
            </template>
            <div class="field turnover-field">
                <label for="turnover_minutes">Cleanup time between bookings (minutes)</label>
                <input class="control" id="turnover_minutes" name="turnover_minutes" type="number" min="0" max="{{ $maxTurnoverMinutes }}" step="5" value="{{ old('turnover_minutes', $turnoverMinutes) }}" required>
                <small>A table stays free for this long after each booking ends so staff can clear and reset it. Use 0 for back-to-back bookings.</small>
            </div>
            <p class="table-help">Tables with reservations can't be removed; untick Bookable to stop new bookings instead. Changes that would leave an upcoming reservation without a table are refused.</p>
            <div class="table-actions">
                <button class="row-add" type="button" data-row-add>+ Add table</button>
                <button class="button" type="submit">Save tables</button>
            </div>
        </form>
    </section>
</div></main></div>
@push('styles')
<style>.table-management>header{margin-bottom:22px}.table-management>header p{font-size:11px;letter-spacing:.15em;color:#7b8308}.table-management>header h1{font-size:30px;margin:6px 0}.table-management>header span,.table-help{color:#687286}.table-management .welcome{padding:24px;max-width:760px;margin-bottom:18px}.table-management h2{margin:0 0 4px;font-size:20px}.table-error{background:#fff0f0;padding:12px;border-radius:9px;margin-bottom:18px}.table-list-scroll{overflow-x:auto}.table-list{width:100%;border-collapse:collapse}.table-list th{text-align:left;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#687286;padding:0 8px 10px}.table-list td{padding:6px 8px;vertical-align:middle}.table-list td:last-child{width:1%;white-space:nowrap}.table-list .control{width:100%;min-width:0}.bookable{display:flex;align-items:center;justify-content:center;min-height:44px}.bookable input{width:20px;height:20px;accent-color:#171817}.row-remove,.row-add{border:1px solid #d3d6cd;border-radius:10px;background:#fff;padding:10px 14px;font-weight:700;cursor:pointer;color:#171817}.row-remove{color:#b3261e}.row-remove:hover{background:#fff0f0}.row-add:hover{background:#f1f2ec}.row-remove:disabled,.row-add:disabled{opacity:.45;cursor:not-allowed;background:#fff}.table-help{font-size:13px;line-height:1.6;margin:8px 8px 16px}.seat-summary{display:flex;flex-wrap:wrap;gap:8px;margin:0 8px 14px}.seat-summary span{background:#f1f2ec;border-radius:999px;padding:5px 11px;font-size:13px;font-weight:700}.turnover-field{margin:16px 8px 0;max-width:340px}.turnover-field small{color:#687286}.table-actions{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:0 8px}.table-actions .button{width:auto;min-width:160px}.visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}@media(max-width:560px){.table-management .welcome{padding:16px}.table-list thead{display:none}.table-list,.table-list tbody{display:block}.table-list tr{display:grid;grid-template-columns:1fr 1fr;gap:8px 10px;padding:12px 0;border-bottom:1px solid #e6e8e0}.layout-list tr{grid-template-columns:1fr 1fr auto}.table-list td{padding:0}.table-list td:last-child{grid-column:1/-1;width:auto}.table-list .visually-hidden{position:static;width:auto;height:auto;clip:auto;display:block;margin-bottom:4px;font-size:12px;font-weight:700;color:#687286}.bookable{flex-direction:column-reverse;align-items:flex-start;min-height:0}.row-remove{width:100%}.table-help{margin:8px 0 14px}.seat-summary,.turnover-field{margin-left:0;margin-right:0}.table-actions{flex-direction:column;align-items:stretch;margin:0}.table-actions .button{width:100%}}</style>
@endpush
@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
(() => {
    let nextIndex = Date.now();

    const refresh = form => {
        const rows = form.querySelectorAll('[data-rows] .editable-row');
        const max = Number(form.dataset.maxRows || 20);
        rows.forEach(row => {
            const remove = row.querySelector('[data-row-remove]');
            if (remove) remove.disabled = rows.length <= 1;
        });
        form.querySelector('[data-row-add]').disabled = rows.length >= max;
    };

    document.addEventListener('click', event => {
        const addButton = event.target.closest('[data-row-add]');
        const removeButton = event.target.closest('[data-row-remove]');
        const form = (addButton || removeButton)?.closest('[data-rows-form]');
        if (!form) return;

        if (addButton) {
            const markup = form.querySelector('[data-row-template]').innerHTML.replaceAll('__INDEX__', String(nextIndex++));
            const body = form.querySelector('[data-rows]');
            body.insertAdjacentHTML('beforeend', markup);
            const row = body.lastElementChild;
            const numberInput = row.querySelector('[data-table-number]');
            if (numberInput) {
                const numbers = [...form.querySelectorAll('[data-table-number]')].map(input => Number(input.value) || 0);
                numberInput.value = Math.max(0, ...numbers) + 1;
            }
            row.querySelector('input:not([type="hidden"])')?.focus();
        } else if (form.querySelectorAll('[data-rows] .editable-row').length > 1) {
            removeButton.closest('.editable-row').remove();
        }

        refresh(form);
    });

    const init = () => document.querySelectorAll('.table-management [data-rows-form]').forEach(refresh);
    document.addEventListener('ajax:content-updated', init);
    init();
})();
</script>
@endpush
@endsection
