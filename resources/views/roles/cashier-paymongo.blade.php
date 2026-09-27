@extends('layouts.app')
@section('title', 'PayMongo QR · Kermit’s POS')
@section('content')
<div class="admin-shell">
    @include('partials.admin-sidebar')
    <main class="admin-workspace qr-workspace">
        <div class="qr-page">
            <header class="qr-head">
                <p>SALE #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }} &middot; PAYMONGO QR PH</p>
                <h1>Ask the customer to scan</h1>
                <span>Any bank or e-wallet app that supports QR Ph (GCash, Maya, BPI, BDO, and more).</span>
            </header>

            @if($errors->any())<div class="qr-error">{{ $errors->first() }}</div>@endif

            <div class="qr-grid">
                <section class="qr-card">
                    <div class="qr-amount"><span>Amount to pay</span><strong>&#8369;{{ number_format($order->total, 2) }}</strong></div>
                    @if($qr)
                        <img class="qr-image" src="{{ $qr }}" alt="QR Ph code for &#8369;{{ number_format($order->total, 2) }}" width="280" height="280">
                    @else
                        <div class="qr-missing">This QR is no longer available on this screen. Cancel the sale and start again.</div>
                    @endif
                    <div class="qr-status" data-qr-status role="status" aria-live="polite"><span class="qr-spinner" aria-hidden="true"></span>Waiting for payment&hellip;</div>
                    <p class="qr-expiry">QR expires in <b data-qr-countdown>--:--</b></p>
                </section>

                <aside class="qr-card qr-summary">
                    <h2>Order</h2>
                    @foreach($order->items as $item)
                        <div class="qr-line"><span>{{ $item->quantity }} &times; {{ $item->product?->name ?? 'Product' }}</span><b>&#8369;{{ number_format($item->subtotal, 2) }}</b></div>
                    @endforeach
                    <div class="qr-line qr-total"><span>Total</span><b>&#8369;{{ number_format($order->total, 2) }}</b></div>
                    <p class="qr-note">The receipt opens automatically once PayMongo confirms the payment. Don&rsquo;t hand over the order until it does.</p>
                    <form method="POST" action="{{ route('cashier.paymongo.cancel', $order) }}" data-qr-cancel data-confirm="Cancel this sale and return its items to stock? If the customer already paid, the sale will be completed instead." data-confirm-title="Cancel PayMongo sale?">
                        @csrf
                        <button class="qr-cancel" type="submit">Cancel sale</button>
                    </form>
                </aside>
            </div>
        </div>
    </main>
</div>
@push('styles')
<style>
.qr-workspace{background:#f3f4ed!important}.qr-page{width:min(980px,100%);margin:auto}.qr-head{margin-bottom:22px}.qr-head p{margin:0 0 6px;color:#7d8600;font-size:10px;font-weight:850;letter-spacing:.14em}.qr-head h1{margin:0;font-size:32px;letter-spacing:-.04em}.qr-head>span{display:block;margin-top:6px;color:#747a71}.qr-error{padding:12px 14px;margin-bottom:18px;border-radius:10px;background:#fff0f0;color:#b42318}.qr-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;align-items:start}.qr-card{background:#fff;border:1px solid #daddd2;border-radius:18px;padding:24px;box-shadow:0 12px 35px rgba(25,27,23,.05)}.qr-card:first-child{display:grid;justify-items:center;text-align:center}.qr-amount{display:grid;gap:3px;margin-bottom:18px}.qr-amount span{color:#767b73;font-size:12px}.qr-amount strong{font-size:34px;letter-spacing:-.03em}.qr-image{width:min(280px,100%);height:auto;aspect-ratio:1;border:1px solid #e1e4da;border-radius:14px;padding:10px;background:#fff;image-rendering:pixelated}.qr-missing{max-width:280px;padding:18px;border-radius:12px;background:#fff0f0;color:#b42318;font-weight:700}.qr-status{display:flex;align-items:center;gap:10px;margin-top:18px;padding:10px 16px;border-radius:999px;background:#f1f2e8;color:#4c5249;font-weight:800;font-size:13px}.qr-status.is-paid{background:#e8f5ed;color:#236b49}.qr-status.is-error{background:#fff0f0;color:#b42318}.qr-spinner{width:14px;height:14px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:qr-spin .8s linear infinite}.qr-status.is-paid .qr-spinner{display:none}.qr-expiry{margin:12px 0 0;color:#747a71;font-size:12px}.qr-summary h2{margin:0 0 12px;font-size:18px}.qr-line{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #eceee7;font-size:13px}.qr-total{border-bottom:0;font-size:16px}.qr-note{margin:14px 0;color:#686e65;font-size:12px;line-height:1.5}.qr-cancel{width:100%;min-height:44px;border:1px solid #b42318;border-radius:11px;background:#fff7f7;color:#b42318;font-weight:800;cursor:pointer}.qr-cancel:hover{background:#b42318;color:#fff}@keyframes qr-spin{to{transform:rotate(360deg)}}
@media(max-width:820px){.qr-grid{grid-template-columns:1fr}.qr-head h1{font-size:27px}}@media(prefers-reduced-motion:reduce){.qr-spinner{animation:none}}
</style>
@endpush
<script nonce="{{ Vite::cspNonce() }}">
(() => {
    const statusUrl = @json(route('cashier.paymongo.status', $order)),
        expiresAt = {{ $expiresAt->getTimestampMs() }},
        status = document.querySelector('[data-qr-status]'),
        countdown = document.querySelector('[data-qr-countdown]'),
        cancelForm = document.querySelector('[data-qr-cancel]');
    let done = false;

    const setStatus = (text, state) => {
        status.lastChild.textContent = text;
        status.classList.toggle('is-paid', state === 'paid');
        status.classList.toggle('is-error', state === 'error');
    };

    async function poll() {
        if (done) return;
        try {
            const response = await fetch(statusUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (response.ok) {
                const data = await response.json();
                if (data.status === 'paid' && data.receipt_url) {
                    done = true;
                    setStatus('Payment received. Opening receipt…', 'paid');
                    window.location.assign(data.receipt_url);
                    return;
                }
                if (data.status !== 'pending') {
                    done = true;
                    setStatus('This sale was cancelled.', 'error');
                    return;
                }
                setStatus('Waiting for payment…');
            }
        } catch (error) {
            setStatus('Connection problem. Still checking…', 'error');
        }
        setTimeout(poll, 3000);
    }

    function tick() {
        if (done) return;
        const left = Math.max(0, Math.round((expiresAt - Date.now()) / 1000));
        countdown.textContent = `${String(Math.floor(left / 60)).padStart(2, '0')}:${String(left % 60).padStart(2, '0')}`;
        if (left === 0) {
            done = true;
            setStatus('QR expired. Cancelling sale…', 'error');
            cancelForm.removeAttribute('data-confirm');
            cancelForm.submit();
            return;
        }
        setTimeout(tick, 1000);
    }

    poll();
    tick();
})();
</script>
@endsection
