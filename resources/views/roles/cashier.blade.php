@extends('layouts.app')
@section('title','Sell · Kermit’s POS')
@section('content')
@php
    $lowStockBelow = 10;
    $stockLabel = fn ($stock) => match (true) {
        $stock < 1 => 'Sold out',
        $stock < $lowStockBelow => 'Low stock · '.$stock.' in stock',
        default => $stock.' in stock',
    };
    $initial = fn (string $name) => Str::upper(Str::substr($name, 0, 1));
@endphp
<div class="admin-shell pos-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard pos-page">
    @if($canSell)<form method="POST" action="{{ route('cashier.checkout') }}" class="pos-layout">@csrf
    @else<div class="pos-layout is-view-only">
    @endif
        <section class="pos-catalog" aria-label="Menu products">
            <header class="pos-catalog-head">
                <div>
                    <h1>Menu</h1>
                    <p>{{ $canSell ? 'Tap a product to add it to the order.' : 'View only — sign in as a cashier to take orders.' }}</p>
                </div>
                <x-search-field id="pos-search" placeholder="Search products" class="pos-search" />
            </header>
            @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="error pos-error">{{ $errors->first() }}</div>@endif
            <div class="pos-category-row">
                <button class="pos-category-arrow" type="button" data-pos-scroll="-1" aria-label="Scroll categories left"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"></path></svg></button>
                <div class="pos-category-tabs" role="group" aria-label="Filter by category">
                    <button class="active" type="button" data-category-filter="all" aria-pressed="true">All</button>
                    @foreach($products->pluck('category')->unique()->values() as $category)<button type="button" data-category-filter="{{ $category }}" aria-pressed="false">{{ $category }}</button>@endforeach
                </div>
                <button class="pos-category-arrow" type="button" data-pos-scroll="1" aria-label="Scroll categories right"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"></path></svg></button>
            </div>
            <p id="pos-filter-summary" class="pos-sr" role="status">Showing: All categories</p>

            <div class="pos-grid">
                @foreach($products->groupBy('category') as $items)@foreach($items as $product)
                @php $imageUrl = $product->imageUrl(); @endphp
                <article @class(['pos-item', 'is-sold-out' => $product->stock < 1])>
                    <div class="pos-item-media">
                        @if($imageUrl)<img class="product-image" src="{{ $imageUrl }}" alt="" loading="lazy">@else<span class="pos-item-placeholder" aria-hidden="true">{{ $initial($product->name) }}</span>@endif
                        @if($canSell)<span class="pos-item-count" data-pos-count hidden></span>@endif
                    </div>
                    <div class="pos-item-body" data-product="{{ $product->id }}" data-name="{{ $product->name }}" data-category="{{ $product->category }}" data-price="{{ $product->price }}" data-stock="{{ $product->stock }}" data-image="{{ $imageUrl }}">
                        <h3>{{ $product->name }}</h3>
                        @if($product->description)<p class="pos-item-desc">{{ $product->description }}</p>@endif
                        <div class="pos-item-meta">
                            <strong>₱{{ number_format($product->price, 2) }}</strong>
                            <span data-pos-stock @class(['low-stock' => $product->stock < $lowStockBelow])>{{ $stockLabel($product->stock) }}</span>
                        </div>
                        @if($canSell)
                        <input class="cart-quantity" name="quantities[{{ $product->id }}]" type="hidden" value="{{ old('quantities.'.$product->id, 0) }}">
                        <button class="add-cart" type="button" aria-label="Add {{ $product->name }} to the order" @disabled($product->stock < 1)><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg><span>Add</span></button>
                        @endif
                    </div>
                </article>
                @endforeach @endforeach
            </div>
            <div class="pos-no-results" data-pos-empty hidden>
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                <p>No products match your search.</p>
            </div>
        </section>

        @if($canSell)
        <aside id="pos-order" class="pos-order" aria-label="Current order">
            <header class="pos-order-head">
                <div><h2>Current order</h2><p id="cart-count">0 items</p></div>
                <button type="button" class="pos-clear" data-pos-clear hidden>Clear</button>
            </header>
            <ul id="cart-items" class="pos-lines" aria-live="polite"></ul>
            <div class="pos-empty-order" data-pos-empty-order>
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3.5 4.5H6l1.5 8.5h10l2-6H6.5"></path><circle cx="9" cy="18" r="1.25"></circle><circle cx="17" cy="18" r="1.25"></circle></svg>
                <p>No items yet</p>
                <small>Tap a product on the menu to add it.</small>
            </div>
            <div class="pos-order-foot">
                <div class="pos-total"><span>Total</span><strong id="cart-total">₱0.00</strong></div>
                <fieldset class="pos-methods">
                    <legend class="pos-sr">Payment method</legend>
                    <label><input type="radio" name="payment_method" value="cash" @checked(old('payment_method', 'cash') === 'cash')><span>Cash</span></label>
                    <label><input type="radio" name="payment_method" value="gcash" @checked(old('payment_method') === 'gcash')><span>GCash</span></label>
                    @if($paymongoEnabled)<label><input type="radio" name="payment_method" value="paymongo" @checked(old('payment_method') === 'paymongo')><span>PayMongo QR Ph</span></label>@endif
                </fieldset>
                <div id="cash-fields" class="pos-pay-fields">
                    <label class="pos-field-label" for="cash_received">Cash received</label>
                    <div class="pos-cash-input"><span>₱</span><input id="cash_received" name="cash_received" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" value="{{ old('cash_received') }}" placeholder="0.00"></div>
                    <div class="pos-quick-cash" data-pos-quick-cash aria-label="Quick cash amounts"></div>
                    <div class="pos-change"><span>Change</span><strong id="change-due">₱0.00</strong></div>
                </div>
                <div id="gcash-fields" class="pos-pay-fields" hidden>
                    <div class="pos-gcash-qr" role="img" aria-label="GCash QR code"></div>
                    <label class="pos-field-label" for="payment_reference">GCash reference number</label>
                    <input class="pos-reference" id="payment_reference" name="payment_reference" type="text" inputmode="numeric" autocomplete="off" value="{{ old('payment_reference') }}" minlength="13" maxlength="13" pattern="[0-9]{13}" placeholder="13-digit reference" aria-describedby="gcash-reference-help">
                    <small id="gcash-reference-help" class="pos-help">Enter exactly 13 digits.</small>
                </div>
                <p id="payment-note" class="pos-note" role="status">Add products to the order.</p>
                <button id="checkout-button" class="pos-checkout" type="submit" disabled><span data-pos-checkout-label>Complete cash sale</span><strong data-pos-checkout-total></strong></button>
            </div>
        </aside>
        <div class="pos-mobile-bar" data-pos-mobile-bar hidden>
            <span><b data-pos-bar-count>0 items</b><strong data-pos-bar-total>₱0.00</strong></span>
            <a href="#pos-order">Review order</a>
        </div>
        @endif
    @if($canSell)</form>@else</div>@endif
</div></main></div>
@push('styles')
<style>
/* POS: the catalog scrolls on the left and the current order stays in view on the right. */
.pos-shell{--pos-ink:#171817;--pos-muted:#6c7068;--pos-line:#dfe1d7;--pos-surface:#fffefa;--pos-accent:#aebb19;--pos-accent-soft:#edf0cf}
.pos-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
.pos-page{min-height:100%}
.pos-layout{display:grid;grid-template-columns:minmax(0,1fr);gap:24px}
.pos-catalog{min-width:0}
.pos-catalog-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:18px}
.pos-catalog-head h1{margin:0;font-size:30px;letter-spacing:-.04em}
.pos-catalog-head p{margin:4px 0 0;color:var(--pos-muted);font-size:14px}
.pos-search{width:min(380px,100%)!important}
.pos-error{background:#fff0f0;padding:12px;border-radius:9px;margin-bottom:16px}

.pos-category-row{display:grid;grid-template-columns:36px minmax(0,1fr) 36px;align-items:center;gap:8px;margin-bottom:18px}
.pos-category-arrow{width:36px;height:36px;display:grid;place-items:center;padding:0;border:1px solid var(--pos-line);border-radius:50%;background:#fff;color:var(--pos-ink);cursor:pointer}
.pos-category-arrow:hover{background:#f1f2eb}
.pos-category-arrow svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.pos-category-tabs{display:flex;gap:8px;overflow-x:auto;scroll-behavior:smooth;scrollbar-width:none;padding:2px}
.pos-category-tabs::-webkit-scrollbar{display:none}
.pos-category-tabs button{flex:0 0 auto;height:40px;padding:0 18px;border:1px solid var(--pos-line);border-radius:999px;background:#fff;color:var(--pos-ink);font:inherit;font-size:14px;font-weight:700;white-space:nowrap;cursor:pointer}
.pos-category-tabs button:hover{border-color:#aeb39f}
.pos-category-tabs button.active{background:var(--pos-ink);border-color:var(--pos-ink);color:#fff}

/* Product tiles: one square picture frame, name, price and stock; the whole tile adds to the order. */
.pos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(168px,1fr));gap:14px}
.pos-item{position:relative;display:flex;flex-direction:column;min-width:0;padding:10px;border:1px solid var(--pos-line);border-radius:16px;background:var(--pos-surface);transition:border-color .15s,box-shadow .15s}
.pos-item[hidden]{display:none}
.pos-item-media{position:relative;aspect-ratio:1;border-radius:12px;overflow:hidden;background:#fff;border:1px solid #eef0e8}
.pos-item-media img{display:block;width:100%;height:100%}
.pos-item-placeholder{width:100%;height:100%;display:grid;place-items:center;background:#f3f4ec;color:#8d960f;font-size:44px;font-weight:800}
.pos-item-count{position:absolute;top:8px;right:8px;min-width:30px;height:30px;display:grid;place-items:center;padding:0 8px;border-radius:999px;background:var(--pos-ink);color:#fff;font-size:14px;font-weight:800;box-shadow:0 4px 12px rgba(23,24,23,.25)}
.pos-item-count[hidden]{display:none}
.pos-item-body{flex:1;display:flex;flex-direction:column;padding:10px 4px 2px;min-width:0}
.pos-item-body h3{margin:0;font-size:15px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pos-item-desc{margin:4px 0 0;color:var(--pos-muted);font-size:12px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:1;line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
.pos-item-meta{display:flex;align-items:baseline;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:auto;padding-top:10px}
.pos-item-meta strong{font-size:17px;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.pos-item-meta span{color:var(--pos-muted);font-size:12px;font-weight:600}
.pos-item-meta span.low-stock{color:#c62828;font-weight:800}
.add-cart{height:38px;margin-top:10px;display:flex;align-items:center;justify-content:center;gap:6px;border:0;border-radius:10px;background:var(--pos-ink);color:#fff;font:inherit;font-size:14px;font-weight:800;cursor:pointer}
.add-cart::after{content:"";position:absolute;inset:0;border-radius:16px}
.add-cart svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round}
.add-cart:focus-visible{outline:3px solid rgba(174,187,25,.5);outline-offset:2px}
.add-cart:disabled{background:#d6d8cf;color:#6c7068;cursor:not-allowed}
.pos-item:has(.add-cart:not(:disabled)):hover{border-color:#aeb39f;box-shadow:0 10px 26px rgba(24,25,22,.08)}
.pos-item:has(.add-cart:not(:disabled)):active{transform:scale(.99)}
.pos-item.is-in-order{border-color:var(--pos-ink);box-shadow:inset 0 0 0 1px var(--pos-ink)}
.pos-item.is-sold-out .pos-item-media{opacity:.45;filter:grayscale(.8)}
.pos-item.is-sold-out h3,.pos-item.is-sold-out .pos-item-meta strong{color:#8a8d85}
.is-view-only .pos-item{padding-bottom:12px}
.pos-no-results{display:grid;justify-items:center;gap:8px;padding:48px 16px;color:var(--pos-muted);text-align:center}
.pos-no-results[hidden]{display:none}
.pos-no-results svg{width:34px;height:34px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round}
.pos-no-results p{margin:0;font-weight:700}

/* Current order */
.pos-order{display:flex;flex-direction:column;border:1px solid var(--pos-line);border-radius:18px;background:var(--pos-surface);box-shadow:0 16px 44px rgba(24,25,22,.08)}
.pos-order-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:20px 20px 14px;border-bottom:1px solid #ecede5}
.pos-order-head h2{margin:0;font-size:20px;letter-spacing:-.02em}
.pos-order-head p{margin:2px 0 0;color:var(--pos-muted);font-size:13px}
.pos-clear{height:32px;padding:0 12px;border:1px solid var(--pos-line);border-radius:8px;background:#fff;color:#b42318;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
.pos-clear[hidden]{display:none}
.pos-clear:hover{background:#fff4f2;border-color:#efc8c5}
.pos-lines{flex:1 1 auto;min-height:0;margin:0;padding:6px 20px;list-style:none;overflow-y:auto}
.pos-lines:empty{display:none}
.pos-line{display:grid;grid-template-columns:44px minmax(0,1fr) auto;grid-template-areas:"thumb info total" "thumb stepper total";align-items:center;column-gap:12px;row-gap:6px;padding:12px 0;border-bottom:1px solid #ecede5}
.pos-line:last-child{border-bottom:0}
.pos-line-thumb{grid-area:thumb;width:44px;height:44px;border-radius:10px;overflow:hidden;background:#f3f4ec;display:grid;place-items:center;color:#8d960f;font-weight:800;border:1px solid #eef0e8}
.pos-line-thumb img{width:100%;height:100%}
.pos-line-info{grid-area:info;min-width:0}
.pos-line-info b{display:block;font-size:14px;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pos-line-info small{color:var(--pos-muted);font-size:12px}
.pos-stepper{grid-area:stepper;display:inline-flex;align-items:center;justify-self:start;border:1px solid var(--pos-line);border-radius:9px;background:#fff}
.pos-stepper button{width:32px;height:30px;padding:0;border:0;background:transparent;color:var(--pos-ink);font-size:18px;font-weight:700;line-height:1;cursor:pointer}
.pos-stepper button:hover:not(:disabled){background:#f1f2eb}
.pos-stepper button:disabled{color:#b8bbb2;cursor:not-allowed}
.pos-stepper output{min-width:28px;text-align:center;font-weight:800;font-variant-numeric:tabular-nums}
.pos-line-total{grid-area:total;font-size:15px;font-variant-numeric:tabular-nums}
.pos-empty-order{flex:1 1 auto;display:grid;align-content:center;justify-items:center;gap:4px;min-height:160px;padding:24px;color:var(--pos-muted);text-align:center}
.pos-empty-order[hidden]{display:none}
.pos-empty-order svg{width:38px;height:38px;margin-bottom:6px;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.pos-empty-order p{margin:0;color:var(--pos-ink);font-weight:800}
.pos-order-foot{display:grid;gap:14px;padding:16px 20px 20px;border-top:1px solid #ecede5;background:#fbfaf5;border-radius:0 0 18px 18px}
.pos-total{display:flex;align-items:baseline;justify-content:space-between}
.pos-total span{font-size:15px;font-weight:700}
.pos-total strong{font-size:30px;letter-spacing:-.03em;font-variant-numeric:tabular-nums}
.pos-methods{display:grid;grid-template-columns:repeat(auto-fit,minmax(0,1fr));gap:8px;margin:0;padding:0;border:0}
.pos-methods label{position:relative;min-height:46px;display:grid;place-items:center;padding:6px 8px;border:1px solid var(--pos-line);border-radius:11px;background:#fff;text-align:center;font-size:13px;font-weight:800;line-height:1.2;cursor:pointer}
.pos-methods input{position:absolute;opacity:0;pointer-events:none}
.pos-methods label:has(input:checked){border-color:var(--pos-ink);background:var(--pos-ink);color:#fff}
.pos-methods label:has(input:focus-visible){outline:3px solid rgba(174,187,25,.5);outline-offset:2px}
.pos-pay-fields{display:grid;gap:8px}
.pos-pay-fields[hidden]{display:none}
.pos-field-label{font-size:13px;font-weight:800}
.pos-cash-input{display:flex;align-items:center;height:52px;border:1px solid #cfd2c8;border-radius:12px;background:#fff}
.pos-cash-input:focus-within{border-color:#8d960f;box-shadow:0 0 0 3px rgba(174,187,25,.18)}
.pos-cash-input span{padding-left:14px;color:var(--pos-muted);font-size:20px;font-weight:700}
.pos-cash-input input{flex:1;min-width:0;height:100%;padding:0 12px 0 6px!important;border:0!important;box-shadow:none!important;background:transparent!important;font:inherit;font-size:22px;font-weight:800;font-variant-numeric:tabular-nums}
.pos-quick-cash{display:flex;flex-wrap:wrap;gap:6px}
.pos-quick-cash:empty{display:none}
.pos-quick-cash button{flex:1 1 0;min-width:64px;height:34px;padding:0 8px;border:1px solid var(--pos-line);border-radius:9px;background:#fff;color:var(--pos-ink);font:inherit;font-size:13px;font-weight:700;font-variant-numeric:tabular-nums;cursor:pointer}
.pos-quick-cash button:hover{background:var(--pos-accent-soft);border-color:#c6cc8d}
.pos-change{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-radius:12px;background:#eef6e9;color:#23683c}
.pos-change span{font-weight:700}
.pos-change strong{font-size:20px;font-variant-numeric:tabular-nums}
.pos-gcash-qr{width:min(180px,100%);aspect-ratio:1;margin:0 auto 4px;border:1px solid var(--pos-line);border-radius:12px;background:#fff url('{{ $gcashQrPath ? route('public.media', ['path' => $gcashQrPath]) : asset('gcash-qr-placeholder.svg') }}') center/contain no-repeat}
.pos-reference{width:100%;height:46px;box-sizing:border-box;border:1px solid #cfd2c8;border-radius:12px;padding:0 12px;font-size:16px;letter-spacing:.06em}
.pos-help{color:var(--pos-muted);font-size:12px}
.pos-note{margin:0;padding:10px 12px;border-radius:10px;background:#f1f2eb;color:#555a52;font-size:13px;line-height:1.45}
.pos-checkout{width:100%;min-height:54px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 20px;border:0;border-radius:14px;background:var(--pos-ink);color:#fff;font:inherit;font-size:16px;font-weight:800;cursor:pointer;box-shadow:0 10px 24px rgba(23,24,23,.18)}
.pos-checkout:hover:not(:disabled){background:#30322e}
.pos-checkout:disabled{background:#c9ccc2;color:#f4f4ef;box-shadow:none;cursor:not-allowed}
.pos-checkout strong{font-variant-numeric:tabular-nums}
.pos-mobile-bar{display:none}

/* Wide screens: catalog and order side by side, each scrolling on its own. */
@media(min-width:1000px){
    .pos-shell .admin-workspace{overflow:hidden!important;padding:0!important}
    .pos-page,.pos-layout{height:100%}
    .pos-layout{grid-template-columns:minmax(0,1fr) clamp(320px,30vw,400px);gap:0}
    .pos-layout.is-view-only{grid-template-columns:minmax(0,1fr)}
    .pos-catalog{overflow-y:auto;padding:clamp(22px,2.6vw,34px)}
    .pos-order{height:100%;min-height:0;border-width:0 0 0 1px;border-radius:0;box-shadow:none}
    .pos-order-foot{border-radius:0}
}
/* Narrower screens: the order follows the menu, and a bar keeps the total in view. */
@media(max-width:999px){
    .pos-shell .admin-workspace{padding-bottom:96px!important}
    .pos-mobile-bar:not([hidden]){position:fixed;z-index:40;left:12px;right:12px;bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:10px 10px 10px 18px;border-radius:16px;background:var(--pos-ink);color:#fff;box-shadow:0 16px 40px rgba(0,0,0,.28)}
    .pos-mobile-bar span{display:grid}
    .pos-mobile-bar b{font-size:12px;font-weight:600;color:#d9dcc7}
    .pos-mobile-bar strong{font-size:18px;font-variant-numeric:tabular-nums}
    .pos-mobile-bar a{height:44px;display:inline-flex;align-items:center;padding:0 18px;border-radius:12px;background:var(--pos-accent);color:var(--pos-ink);font-weight:800;text-decoration:none}
    .pos-lines{max-height:none;overflow:visible}
}
@media(max-width:640px){
    .pos-catalog-head{flex-direction:column;align-items:stretch;gap:12px}
    .pos-catalog-head h1{font-size:26px}
    .pos-search{width:100%!important}
    .pos-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .pos-item{padding:8px;border-radius:14px}
    .pos-item-desc{display:none}
    .pos-item-meta strong{font-size:15px}
    .pos-item-meta span{font-size:11px}
    .add-cart{height:36px}
    .pos-category-row{grid-template-columns:minmax(0,1fr)}
    .pos-category-arrow{display:none}
    .pos-cash-input input{font-size:20px}
}
/* Keep the "Enable sound & desktop alerts" prompt off the checkout button and the order bar. */
@media(min-width:1000px){body:has(.pos-shell) .cashier-alert-setup{right:calc(clamp(320px,30vw,400px) + 40px)}}
@media(max-width:999px){body:has(.pos-mobile-bar:not([hidden])) .cashier-alert-setup{bottom:96px}}
@media(prefers-reduced-motion:reduce){.pos-item{transition:none}.pos-item:active{transform:none}}
</style>
@endpush


@if($canSell)
<script nonce="{{ Vite::cspNonce() }}">
(() => {
    const LOW_STOCK_BELOW = {{ $lowStockBelow }};
    const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
    const lines = document.getElementById('cart-items');
    const count = document.getElementById('cart-count');
    const totalElement = document.getElementById('cart-total');
    const emptyOrder = document.querySelector('[data-pos-empty-order]');
    const clearButton = document.querySelector('[data-pos-clear]');
    const mobileBar = document.querySelector('[data-pos-mobile-bar]');
    const cash = document.getElementById('cash_received');
    const reference = document.getElementById('payment_reference');
    const submit = document.getElementById('checkout-button');
    const note = document.getElementById('payment-note');
    const change = document.getElementById('change-due');
    const quickCash = document.querySelector('[data-pos-quick-cash]');
    const products = new Map([...document.querySelectorAll('[data-product]')].map(row => {
        const card = row.closest('.pos-item');
        return [row.dataset.product, {
            id: row.dataset.product,
            name: row.dataset.name,
            price: Number(row.dataset.price),
            stock: Number(row.dataset.stock),
            image: row.dataset.image,
            input: row.querySelector('.cart-quantity'),
            stockLabel: row.querySelector('[data-pos-stock]'),
            add: row.querySelector('.add-cart'),
            card,
            badge: card.querySelector('[data-pos-count]'),
        }];
    }));
    const quantityOf = product => Number(product.input.value) || 0;
    const order = () => [...products.values()].filter(product => quantityOf(product) > 0);
    const orderTotal = () => order().reduce((sum, product) => sum + product.price * quantityOf(product), 0);
    const orderQuantity = () => order().reduce((sum, product) => sum + quantityOf(product), 0);
    let quickCashFor = null;

    const element = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const buildLine = product => {
        const quantity = quantityOf(product);
        const line = element('li', 'pos-line');
        const thumb = element('span', 'pos-line-thumb');
        if (product.image) {
            const image = element('img', 'product-image');
            image.src = product.image;
            image.alt = '';
            thumb.append(image);
        } else {
            thumb.textContent = [...product.name][0]?.toLocaleUpperCase() ?? '';
        }
        const info = element('span', 'pos-line-info');
        info.append(element('b', '', product.name), element('small', '', `${money(product.price)} each`));
        const stepper = element('span', 'pos-stepper');
        const minus = element('button', '', '−');
        minus.type = 'button';
        minus.dataset.id = product.id;
        minus.dataset.step = '-1';
        minus.setAttribute('aria-label', `Remove one ${product.name}`);
        const plus = element('button', '', '+');
        plus.type = 'button';
        plus.dataset.id = product.id;
        plus.dataset.step = '1';
        plus.disabled = quantity >= product.stock;
        plus.setAttribute('aria-label', `Add one more ${product.name}`);
        stepper.append(minus, element('output', '', String(quantity)), plus);
        line.append(thumb, info, stepper, element('strong', 'pos-line-total', money(product.price * quantity)));
        return line;
    };

    // Suggest the exact amount plus the next common bill totals above it.
    const renderQuickCash = total => {
        if (quickCashFor === total) return;
        quickCashFor = total;
        quickCash.replaceChildren();
        if (!total) return;
        const amounts = [total, ...[50, 100, 500, 1000].map(unit => Math.ceil(total / unit) * unit).filter(amount => amount > total)];
        [...new Set(amounts)].slice(0, 4).forEach((amount, index) => {
            const button = element('button', '', index === 0 ? 'Exact' : money(amount).replace(/\.00$/, ''));
            button.type = 'button';
            button.dataset.amount = amount.toFixed(2);
            quickCash.append(button);
        });
    };

    const updatePayment = () => {
        const method = document.querySelector('input[name="payment_method"]:checked')?.value || 'cash';
        const quantity = orderQuantity();
        const total = orderTotal();
        const paid = Number(cash.value || 0);
        const hasReference = /^\d{13}$/.test(reference.value);
        const isCash = method === 'cash';
        const isGcash = method === 'gcash';
        document.getElementById('cash-fields').hidden = !isCash;
        document.getElementById('gcash-fields').hidden = !isGcash;
        cash.required = isCash;
        cash.disabled = !isCash;
        reference.required = isGcash;
        reference.disabled = !isGcash;
        renderQuickCash(total);

        if (isCash) {
            change.textContent = money(quantity && paid >= total ? paid - total : 0);
            submit.disabled = !(quantity && paid >= total);
            note.textContent = !quantity ? 'Add products to the order.'
                : !paid ? 'Enter the cash received from the customer.'
                : paid < total ? `Cash is short by ${money(total - paid)}.`
                : `Give ${money(paid - total)} change.`;
        } else if (isGcash) {
            submit.disabled = !(quantity && hasReference);
            note.textContent = !quantity ? 'Add products to the order.'
                : !hasReference ? 'Enter the complete 13-digit GCash reference number.'
                : 'Reference looks complete. Confirm the payment before completing the sale.';
        } else {
            submit.disabled = !quantity;
            note.textContent = !quantity ? 'Add products to the order.'
                : `A one-time QR for ${money(total)} will be shown. The sale completes once PayMongo confirms the payment.`;
        }
        submit.querySelector('[data-pos-checkout-label]').textContent = isCash ? 'Complete cash sale' : isGcash ? 'Complete GCash sale' : 'Show PayMongo QR';
        submit.querySelector('[data-pos-checkout-total]').textContent = quantity ? money(total) : '';
    };

    const draw = () => {
        const selected = order();
        const quantity = orderQuantity();
        const total = orderTotal();
        const focused = document.activeElement?.closest?.('.pos-stepper button');
        const restoreFocus = focused ? { id: focused.dataset.id, step: focused.dataset.step } : null;

        lines.replaceChildren(...selected.map(buildLine));
        emptyOrder.hidden = selected.length > 0;
        clearButton.hidden = selected.length === 0;
        count.textContent = `${quantity} ${quantity === 1 ? 'item' : 'items'}`;
        totalElement.textContent = money(total);
        if (mobileBar) {
            mobileBar.hidden = quantity === 0;
            mobileBar.querySelector('[data-pos-bar-count]').textContent = count.textContent;
            mobileBar.querySelector('[data-pos-bar-total]').textContent = money(total);
        }

        products.forEach(product => {
            const inOrder = quantityOf(product);
            const remaining = Math.max(0, product.stock - inOrder);
            product.stockLabel.textContent = product.stock < 1 ? 'Sold out'
                : remaining === 0 ? `All ${product.stock} in order`
                : remaining < LOW_STOCK_BELOW ? `Low stock · ${remaining} in stock`
                : `${remaining} in stock`;
            product.stockLabel.classList.toggle('low-stock', remaining < LOW_STOCK_BELOW);
            product.add.disabled = remaining === 0;
            product.card.classList.toggle('is-in-order', inOrder > 0);
            product.badge.hidden = inOrder === 0;
            product.badge.textContent = String(inOrder);
        });

        if (restoreFocus) {
            const target = lines.querySelector(`button[data-id="${restoreFocus.id}"][data-step="${restoreFocus.step}"]`);
            (target && !target.disabled ? target : products.get(restoreFocus.id)?.add)?.focus({ preventScroll: true });
        }
        updatePayment();
    };

    const adjust = (id, step) => {
        const product = products.get(id);
        if (!product) return;
        product.input.value = String(Math.max(0, Math.min(product.stock, quantityOf(product) + step)));
        draw();
    };

    products.forEach(product => product.add.addEventListener('click', () => adjust(product.id, 1)));
    lines.addEventListener('click', event => {
        const button = event.target.closest('[data-step]');
        if (button) adjust(button.dataset.id, Number(button.dataset.step));
    });
    clearButton.addEventListener('click', () => {
        products.forEach(product => { product.input.value = '0'; });
        cash.value = '';
        draw();
    });
    quickCash.addEventListener('click', event => {
        const button = event.target.closest('[data-amount]');
        if (!button) return;
        cash.value = button.dataset.amount;
        updatePayment();
    });
    document.querySelectorAll('input[name="payment_method"]').forEach(input => input.addEventListener('change', updatePayment));
    cash.addEventListener('input', updatePayment);
    reference.addEventListener('input', () => {
        reference.value = reference.value.replace(/\D/g, '').slice(0, 13);
        updatePayment();
    });
    draw();
})();
</script>
@endif

<script nonce="{{ Vite::cspNonce() }}">
(() => {
    const search = document.getElementById('pos-search');
    const buttons = [...document.querySelectorAll('[data-category-filter]')];
    const rows = [...document.querySelectorAll('[data-product]')];
    const summary = document.getElementById('pos-filter-summary');
    const empty = document.querySelector('[data-pos-empty]');
    const tabs = document.querySelector('.pos-category-tabs');
    const normalize = value => (value || '').trim().toLocaleLowerCase();
    let category = 'all';

    const apply = () => {
        const term = normalize(search?.value);
        const categoryName = buttons.find(button => button.dataset.categoryFilter === category)?.textContent.trim() || 'All categories';
        let visible = 0;
        rows.forEach(row => {
            const matchesCategory = category === 'all' || normalize(row.dataset.category) === normalize(category);
            const matchesText = !term || normalize(row.dataset.name).includes(term) || normalize(row.dataset.category).includes(term);
            row.closest('.pos-item').hidden = !(matchesCategory && matchesText);
            if (matchesCategory && matchesText) visible++;
        });
        if (empty) empty.hidden = visible > 0;
        if (summary) summary.textContent = `Showing: ${categoryName} (${visible} ${visible === 1 ? 'product' : 'products'})`;
    };

    buttons.forEach(button => button.addEventListener('click', () => {
        buttons.forEach(item => {
            item.classList.toggle('active', item === button);
            item.setAttribute('aria-pressed', String(item === button));
        });
        category = button.dataset.categoryFilter;
        button.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        apply();
    }));
    document.querySelectorAll('[data-pos-scroll]').forEach(button => button.addEventListener('click', () => {
        tabs?.scrollBy({ left: Number(button.dataset.posScroll) * 260, behavior: 'smooth' });
    }));
    search?.addEventListener('input', apply);
    apply();
})();
</script>
@endsection
