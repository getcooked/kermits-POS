@php($pickerId = $pickerId ?? 'dining_table_id')
@if(($diningTables ?? collect())->isNotEmpty())
    <div class="table-picker">
        <label for="{{ $pickerId }}">Table (optional)</label>
        <select class="control" id="{{ $pickerId }}" name="dining_table_id" data-table-picker>
            <option value="">Any available table (recommended)</option>
            @foreach($diningTables as $diningTable)
                <option value="{{ $diningTable->id }}" data-seats="{{ $diningTable->seats }}" @selected((string) old('dining_table_id') === (string) $diningTable->id)>Table {{ $diningTable->number }} &middot; {{ $diningTable->seats }} {{ $diningTable->seats === 1 ? 'seat' : 'seats' }}</option>
            @endforeach
        </select>
        <small>Only tables that fit your party are listed. Choosing a specific table can leave fewer times available.</small>
        @error('dining_table_id')<p class="error" role="alert">{{ $message }}</p>@enderror
    </div>
    @once
        @push('styles')
            <style>.table-picker{display:grid;gap:6px;margin-top:12px}.table-picker label{margin:0}.table-picker small{color:#687064}</style>
        @endpush
    @endonce
@endif
