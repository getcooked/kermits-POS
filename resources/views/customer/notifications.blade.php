@extends('layouts.app')
@section('title', 'Notifications | Kermit\'s')

@section('content')
<main class="history-page customer-account-page">
    @include('customer.navigation', ['activeCustomerNav' => 'notifications'])

    <header class="account-header notification-header">
        <div>
            <p>MY ACCOUNT</p>
            <h1>Notifications</h1>
            <span>Reservation and order decisions from Kermit's appear here.</span>
        </div>
        @if($customerUnreadNotificationCount)
            <form method="POST" action="{{ route('customer.notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button type="submit">Mark all as read</button>
            </form>
        @endif
    </header>

    <section class="account-content">
        @if(session('status'))<div class="account-notice" role="status">{{ session('status') }}</div>@endif

        <div class="notification-list">
            @forelse($notifications as $notification)
                @php
                    $status = $notification->data['status'] ?? 'updated';
                    $accepted = in_array($status, ['confirmed', 'paid'], true);
                @endphp
                <article @class(['notification-item', 'unread' => is_null($notification->read_at)])>
                    <span @class(['notification-icon', 'accepted' => $accepted, 'rejected' => ! $accepted]) aria-hidden="true">{{ $accepted ? '✓' : '×' }}</span>
                    <div>
                        <div class="notification-title">
                            <strong>{{ $notification->data['title'] ?? 'Status updated' }}</strong>
                            @if(is_null($notification->read_at))<span>New</span>@endif
                        </div>
                        <p>{{ $notification->data['message'] ?? 'Your request has a new status.' }}</p>
                        <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                    </div>
                    <form method="POST" action="{{ route('customer.notifications.read', $notification) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit">View details</button>
                    </form>
                </article>
            @empty
                <div class="notification-empty">
                    <span aria-hidden="true">✓</span>
                    <h2>You are all caught up</h2>
                    <p>Accepted or rejected reservations and orders will appear here.</p>
                    <a href="{{ route('shop') }}">Back to menu</a>
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="notification-pages">{{ $notifications->onEachSide(1)->links() }}</div>
        @endif
    </section>
</main>

@push('styles')
    @include('customer.account-styles')
    <style>
    .notification-header{display:flex;align-items:end;justify-content:space-between;gap:22px}.notification-header>div>span{color:#687064;font-size:14px}.notification-header form{margin:0}.notification-header button,.notification-item button{min-height:42px;padding:0 15px;border:1px solid #cfd3c8;border-radius:7px;background:#fff;color:#242622;font-weight:800;cursor:pointer;white-space:nowrap}.notification-list{display:grid;gap:10px}.notification-item{display:grid;grid-template-columns:46px minmax(0,1fr) auto;align-items:center;gap:16px;padding:18px;border:1px solid #d7dacf;border-radius:11px;background:#fff}.notification-item.unread{border-left:4px solid #aab514;background:#fffffb}.notification-icon{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;font-size:23px;font-weight:900}.notification-icon.accepted{background:#e2f3e7;color:#247141}.notification-icon.rejected{background:#fde9e9;color:#b52b2b}.notification-title{display:flex;align-items:center;gap:8px}.notification-title strong{font-size:16px}.notification-title span{padding:3px 7px;border-radius:999px;background:#e9edc3;color:#596100;font-size:10px;font-weight:900;text-transform:uppercase}.notification-item p{margin:5px 0;color:#5f665c;font-size:13px;line-height:1.5}.notification-item time{color:#858b82;font-size:11px}.notification-item button{background:#171817;color:#fff;border-color:#171817}.notification-empty{padding:60px 24px;border:1px dashed #cbd0c3;border-radius:11px;text-align:center}.notification-empty>span{width:56px;height:56px;margin:0 auto 16px;border-radius:50%;background:#e7f3e9;color:#247141;display:grid;place-items:center;font-size:26px;font-weight:900}.notification-empty h2{margin:0;font-size:20px}.notification-empty p{margin:8px 0 19px;color:#70766d}.notification-empty a{display:inline-flex;min-height:42px;padding:0 16px;border-radius:7px;background:#171817;color:#fff;align-items:center;text-decoration:none;font-size:13px;font-weight:800}.notification-pages{margin-top:18px}.notification-pages nav{display:flex;justify-content:center}.notification-pages svg{width:18px}.notification-pages p{display:none}
    @media(max-width:620px){.notification-header{align-items:stretch;display:grid}.notification-header button{width:100%}.notification-item{grid-template-columns:40px minmax(0,1fr);gap:12px;padding:15px}.notification-icon{width:40px;height:40px}.notification-item form{grid-column:1/-1}.notification-item button{width:100%}}
    </style>
@endpush
@endsection
