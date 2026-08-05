@extends('layouts.app')
@section('title', 'Review Customer Order · Kermit’s POS')
@section('content')
<div class="admin-shell">
    @include('partials.admin-sidebar')
    <main class="admin-workspace review-workspace">
        <div class="review-page">
            <header class="review-head">
                <a href="{{ route('cashier.orders.index') }}">← Customer orders</a>
                <p>ORDER #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</p>
                <h1>Review complete order</h1>
                <span>{{ $order->customer?->name }} · {{ $order->customer?->email }}</span>
            </header>

            @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="review-error">{{ $errors->first() }}</div>@endif

            <div class="review-grid">
                <section class="review-card">
                    <div class="card-title"><div><p>ORDER ITEMS</p><h2>Edit what the customer really wants</h2></div><span>Use 0 to remove an item</span></div>
                    <form method="POST" action="{{ route('cashier.orders.update', $order) }}">
                        @csrf @method('PUT')
                        <div class="editable-items">
                            @foreach($order->items as $item)
                                <label data-price="{{ $item->unit_price }}">
                                    <span><b>{{ $item->product?->name ?? 'Unavailable product' }}</b><small>&#8369;{{ number_format($item->unit_price, 2) }} each · {{ $item->product?->stock ?? 0 }} more available</small></span>
                                    <input class="review-quantity" name="quantities[{{ $item->id }}]" type="number" min="0" max="22" value="{{ old('quantities.'.$item->id, $item->quantity) }}" required>
                                </label>
                            @endforeach
                        </div>
                        <div class="edit-total"><span>Reviewed total</span><strong id="review-total">&#8369;{{ number_format($order->total, 2) }}</strong></div>
                        <button class="save-review" type="submit">Save order changes</button>
                    </form>
                </section>

                <aside class="review-card payment-review">
                    <div class="card-title"><div><p>PAYMENT CHECK</p><h2>Confirm payment</h2></div></div>
                    @if($order->payment_method === 'gcash')
                        <div class="payment-state paid"><span>✓</span><div><b>Already submitted through GCash</b><small>Verify this reference before confirming.</small></div></div>
                        <div class="reference-box"><span>GCash reference</span><strong>{{ $order->payment_reference }}</strong></div>
                    @else
                        <div class="payment-state unpaid"><span>!</span><div><b>Not yet paid</b><small>The customer must pay the reviewed total in cash.</small></div></div>
                    @endif

                    <div class="confirm-total"><span>Amount due</span><strong>&#8369;{{ number_format($order->total, 2) }}</strong></div>
                    <form method="POST" action="{{ route('cashier.orders.confirm-payment', $order) }}" onsubmit="return confirm('Confirm that the payment for this complete order has been received?')">
                        @csrf @method('PATCH')
                        @if($order->payment_method === 'cash')
                            <label for="cash_received">Customer cash</label>
                            <div class="cash-entry"><span>&#8369;</span><input id="cash_received" name="cash_received" type="number" min="{{ $order->total }}" max="99999999.99" step="0.01" value="{{ old('cash_received') }}" required placeholder="0.00"></div>
                            <div class="change-preview"><span>Change</span><strong id="cash-change">&#8369;0.00</strong></div>
                        @endif
                        <button class="confirm-payment" type="submit">{{ $order->payment_method === 'gcash' ? 'Verify GCash and confirm paid' : 'Receive cash and confirm paid' }} <span>→</span></button>
                    </form>
                    <p class="confirm-note">Confirming sends this sale to the Admin and Super Admin dashboards and reports.</p>
                </aside>
            </div>
        </div>
    </main>
</div>
<style>
.review-workspace{background:#f3f4ed!important}.review-page{width:min(1120px,100%);margin:auto}.review-head{margin-bottom:22px}.review-head>a{display:inline-flex;margin-bottom:20px;color:#606800;font-size:12px;font-weight:800}.review-head p,.card-title p{margin:0 0 6px;color:#7d8600;font-size:10px;font-weight:850;letter-spacing:.14em}.review-head h1{font-size:32px;margin:0;letter-spacing:-.04em}.review-head>span{display:block;color:#747a71;margin-top:6px}.review-error{padding:12px 14px;margin-bottom:18px;border-radius:10px;background:#fff0f0;color:#b42318}.review-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;align-items:start}.review-card{background:#fff;border:1px solid #daddd2;border-radius:18px;padding:22px;box-shadow:0 12px 35px rgba(25,27,23,.05)}.card-title{display:flex;justify-content:space-between;align-items:flex-start;gap:15px;padding-bottom:15px;border-bottom:1px solid #e5e7df}.card-title h2{margin:0;font-size:18px}.card-title>span{font-size:11px;color:#777d74}.editable-items{display:grid}.editable-items label{display:grid;grid-template-columns:minmax(0,1fr) 82px;align-items:center;gap:16px;padding:14px 0;border-bottom:1px solid #eceee7;margin:0}.editable-items label>span{display:grid}.editable-items small{color:#777d74;margin-top:4px}.editable-items input{width:82px;text-align:center}.edit-total,.confirm-total,.change-preview{display:flex;justify-content:space-between;align-items:center}.edit-total{padding:18px 0}.edit-total strong,.confirm-total strong{font-size:23px}.save-review,.confirm-payment{width:100%;min-height:46px;border:0;border-radius:11px;padding:0 16px;background:#171817;color:#fff;font-weight:800;cursor:pointer}.payment-review{position:sticky;top:0}.payment-state{display:flex;gap:11px;align-items:center;border-radius:12px;padding:14px;margin:16px 0}.payment-state>span{width:34px;height:34px;flex:0 0 auto;border-radius:50%;display:grid;place-items:center;font-weight:900}.payment-state>div{display:grid}.payment-state small{margin-top:3px}.payment-state.paid{background:#e8f5ed;color:#236b49}.payment-state.paid>span{background:#cae8d6}.payment-state.unpaid{background:#fff1e8;color:#9b4b16}.payment-state.unpaid>span{background:#f5d5bf}.reference-box{display:grid;gap:5px;padding:13px;border:1px dashed #9fb4d2;border-radius:11px;background:#f3f7ff}.reference-box span{font-size:11px;color:#66758a}.reference-box strong{font-size:19px;letter-spacing:.08em}.confirm-total{padding:19px 0;border-bottom:1px solid #e4e6df;margin-bottom:16px}.payment-review form>label{font-size:12px;font-weight:800}.cash-entry{display:flex;align-items:center;border:1px solid #cfd3c7;border-radius:10px;margin:7px 0 10px}.cash-entry span{padding-left:11px}.cash-entry input{width:100%;border:0!important;box-shadow:none!important;font-size:18px}.change-preview{padding:12px;background:#eff1df;border-radius:10px;margin-bottom:14px}.confirm-payment{display:flex;align-items:center;justify-content:space-between}.confirm-note{font-size:11px;line-height:1.45;color:#777d74;margin:12px 0 0}
@media(max-width:900px){.review-grid{grid-template-columns:1fr}.payment-review{position:static;order:-1}}@media(max-width:560px){.review-card{padding:17px}.card-title{flex-direction:column}.editable-items label{grid-template-columns:minmax(0,1fr) 70px}.editable-items input{width:70px}.review-head h1{font-size:27px}}
</style>
<script>
(() => {
    const quantities=[...document.querySelectorAll('.review-quantity')],total=document.getElementById('review-total');
    const money=value=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(value);
    const update=()=>{total.textContent=money(quantities.reduce((sum,input)=>sum+(+input.value||0)*(+input.closest('label').dataset.price),0))};
    quantities.forEach(input=>input.addEventListener('input',update)); update();
    const cash=document.getElementById('cash_received'),change=document.getElementById('cash-change');
    if(cash){const due={{ (float) $order->total }};const updateCash=()=>change.textContent=money(Math.max(0,(+cash.value||0)-due));cash.addEventListener('input',updateCash);updateCash()}
})();
</script>
@endsection
