<aside class="admin-sidebar">
    <a class="admin-brand" href="{{ auth()->user()->homeRoute() }}"><img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's"><strong>KERMIT'S</strong></a>
    <nav>
        @if(auth()->user()->hasRole('cashier'))
            <a class="{{ request()->routeIs('cashier') || request()->routeIs('cashier.checkout') ? 'active' : '' }}" href="{{ route('cashier') }}">@include('partials.nav-icon',['name'=>'pos']) POS</a>
            <a class="{{ request()->routeIs('cashier.orders.*') ? 'active' : '' }}" href="{{ route('cashier.orders.index') }}">@include('partials.nav-icon',['name'=>'orders']) Customer Orders</a>
        @elseif(auth()->user()->hasRole('super_admin', 'admin'))
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">@include('partials.nav-icon',['name'=>'home']) Dashboard</a>
            @if(auth()->user()->hasRole('super_admin'))
            <a class="{{ request()->routeIs('superadmin.security.*') ? 'active' : '' }}" href="{{ route('superadmin.security.edit') }}">@include('partials.nav-icon',['name'=>'security']) Security</a>
            <a class="{{ request()->routeIs('cashier') || request()->routeIs('cashier.checkout') ? 'active' : '' }}" href="{{ route('cashier') }}">@include('partials.nav-icon',['name'=>'pos']) POS</a>
            <a class="{{ request()->routeIs('reports') ? 'active' : '' }}" href="{{ route('reports') }}">@include('partials.nav-icon',['name'=>'reports']) Reporting</a>
            <a class="{{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">@include('partials.nav-icon',['name'=>'inventory']) Inventory</a>
            <a class="{{ request()->routeIs('reservations.index') ? 'active' : '' }}" href="{{ route('reservations.index') }}">@include('partials.nav-icon',['name'=>'reservations']) Reservations</a>
            <a class="{{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">@include('partials.nav-icon',['name'=>'products']) Menu Pictures</a>
            <a class="{{ request()->routeIs('admins.*') ? 'active' : '' }}" href="{{ route('admins.index') }}">@include('partials.nav-icon',['name'=>'users']) Admin Accounts</a>
            <a class="{{ request()->routeIs('cashiers.*') ? 'active' : '' }}" href="{{ route('cashiers.index') }}">@include('partials.nav-icon',['name'=>'cashier']) Cashier Accounts</a>
            <a class="{{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">@include('partials.nav-icon',['name'=>'users']) Customers</a>
            <a class="{{ request()->routeIs('activity-logs.*') ? 'active' : '' }}" href="{{ route('activity-logs.index') }}">@include('partials.nav-icon',['name'=>'activity']) Activity Logs</a>
            <a class="{{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.payment.edit') }}">@include('partials.nav-icon',['name'=>'settings']) Settings</a>
            @endif
        @endif
    </nav>
    <div class="admin-user"><div><strong>Hi, {{ auth()->user()->name }}</strong><small>{{ str(auth()->user()->role)->replace('_',' ')->title() }}</small></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-icon" type="submit" title="Log out" aria-label="Log out"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M18 12H9"/></svg></button></form></div>
</aside>
