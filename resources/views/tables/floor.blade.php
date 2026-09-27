@extends('layouts.app')
@section('title', 'Tables')
@section('content')
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard table-management">
    <header>
        <p>FLOOR</p>
        <h1>Tables</h1>
        <span>See which tables are free and mark them occupied or free as guests arrive and leave.</span>
    </header>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error table-error" role="alert">{{ $errors->first() }}</div>@endif

    @include('tables.partials.floor')
    @if($floor['tables'] === [])
        <section class="welcome"><p class="table-help">Tables aren't set up yet. Ask the super admin to add them in Table Management.</p></section>
    @endif
</div></main></div>
@push('styles')
@include('tables.partials.styles')
@endpush
@endsection
