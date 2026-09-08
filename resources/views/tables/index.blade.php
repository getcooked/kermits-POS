@extends('layouts.app')
@section('title', 'Numbered tables')
@section('content')
<div class="admin-shell">
    @include('partials.admin-sidebar')
    <main class="admin-workspace"><div class="dashboard">
        <header class="topbar"><div><h1>Numbered tables</h1><p class="muted">Manage your physical tables and their seating capacity.</p></div><a href="{{ route('reservations.index') }}">Reservations</a></header>
        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div role="alert" class="notice">{{ $errors->first() }}</div>@endif
        <section class="welcome table-policy"><strong>Open daily: 8:00 AM–11:00 PM</strong><p>Two-hour reservations, ending by closing time. Last reservation: 10:00–11:00 PM. Pending reservations hold a table for 30 minutes, or until arrival if sooner.</p><p>Tables are assigned automatically, smallest suitable table first. Exclusive reservations reserve the entire venue. Reassign active bookings before taking a table out of service.</p></section>
        <section class="table-grid" aria-label="Dining tables">
        @foreach($tables as $table)
            <form class="welcome table-editor" method="POST" action="{{ route('tables.update', $table) }}">
                @csrf @method('PUT')
                <h2>Table {{ $table->number }}</h2>
                <label for="number-{{ $table->id }}">Table number</label><input class="control" id="number-{{ $table->id }}" name="number" type="number" min="1" max="9999" value="{{ $table->number }}" required>
                <label for="capacity-{{ $table->id }}">Seats</label><input class="control" id="capacity-{{ $table->id }}" name="capacity" type="number" min="1" max="300" value="{{ $table->capacity }}" required>
                <label for="active-{{ $table->id }}">Service status</label><select class="control" id="active-{{ $table->id }}" name="active"><option value="1" @selected($table->active)>In service</option><option value="0" @selected(!$table->active)>Out of service</option></select>
                <button class="button">Save table {{ $table->number }}</button>
            </form>
        @endforeach
            <form class="welcome table-editor" method="POST" action="{{ route('tables.store') }}">
                @csrf <h2>Add a table</h2>
                <label for="new-number">Table number</label><input class="control" id="new-number" name="number" type="number" min="1" max="9999" required>
                <label for="new-capacity">Seats</label><input class="control" id="new-capacity" name="capacity" type="number" min="1" max="300" required>
                <button class="button">Add table</button>
            </form>
        </section>
    </div></main>
</div>
<style>.table-policy{padding:20px;margin-bottom:20px}.table-policy p{line-height:1.6}.table-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.table-editor{padding:20px;display:grid;gap:8px;align-content:start}.table-editor h2{margin:0 0 8px}.table-editor .button{margin-top:10px}@media(max-width:500px){.table-grid{grid-template-columns:1fr}}</style>
@endsection
