<nav class="account-section-tabs" aria-label="Account sections">
    <a class="{{ request()->routeIs('superadmin.security.*') ? 'active' : '' }}" href="{{ route('superadmin.security.edit') }}">Admin Account</a>
    <a class="{{ request()->routeIs('cashiers.*') ? 'active' : '' }}" href="{{ route('cashiers.index') }}">Cashier Accounts</a>
    <a class="{{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">Customers</a>
</nav>

@once
    @push('styles')
        <style>
        .account-section-tabs{display:flex;gap:5px;margin:0 0 20px;padding:5px;border:1px solid #daddd1;border-radius:13px;background:#e9ebe4;overflow-x:auto;scrollbar-width:none}.account-section-tabs::-webkit-scrollbar{display:none}.account-section-tabs a{min-height:40px;padding:0 16px;border-radius:9px;color:#646a61;display:grid;place-items:center;text-decoration:none;white-space:nowrap;font-size:12px;font-weight:800}.account-section-tabs a:hover{background:#f5f6f1;color:#292b27}.account-section-tabs a.active{background:#171817;color:#fff;box-shadow:0 6px 15px rgba(23,24,23,.16)}@media(max-width:560px){.account-section-tabs a{flex:1;padding:0 11px;font-size:11px}}
        </style>
    @endpush
@endonce
