<nav aria-label="Customer navigation">
    <a class="history-brand" href="{{ route('home') }}">
        <img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's">
        <strong>KERMIT'S</strong>
    </a>

    <div class="history-actions">
        <a @class(['active' => ($activeCustomerNav ?? '') === 'menu']) href="{{ route('shop') }}" @if(($activeCustomerNav ?? '') === 'menu') aria-current="page" @endif>@include('partials.nav-icon', ['name' => 'products']) Menu</a>
        <a @class(['active' => ($activeCustomerNav ?? '') === 'history']) href="{{ route('customer.history') }}" @if(($activeCustomerNav ?? '') === 'history') aria-current="page" @endif>@include('partials.nav-icon', ['name' => 'activity']) History</a>
        @if($appDownloadAvailable)
            <a class="history-app-link" href="{{ $appDownloadUrl }}" download>@include('partials.nav-icon', ['name' => 'app'])<span>Download app</span><b>App</b></a>
        @else
            <a class="history-app-link disabled" aria-disabled="true">@include('partials.nav-icon', ['name' => 'app'])<span>App coming soon</span><b>App</b></a>
        @endif
        <span class="customer-user"><strong>Hi, {{ auth()->user()->name }}</strong><small>Customer</small></span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="logout-icon" type="submit" title="Log out" aria-label="Log out">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M18 12H9"/></svg>
            </button>
        </form>
    </div>
</nav>
