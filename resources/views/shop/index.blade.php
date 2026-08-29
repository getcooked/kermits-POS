@extends('layouts.app')
@section('title', "Menu | Kermit's")
@section('content')
<main class="customer-shop">
    <nav>
        <a href="{{ route('home') }}"><img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's"><strong>KERMIT'S</strong></a>
        <div class="customer-actions"><a class="active" href="{{ route('shop') }}" aria-current="page">Menu</a><a href="{{ route('customer.history') }}">History</a>@if($appDownloadAvailable)<a class="customer-app-link" href="{{ $appDownloadUrl }}" download><span>Download app</span><b>App</b></a>@else<a class="customer-app-link disabled" aria-disabled="true"><span>App coming soon</span><b>App</b></a>@endif<span>Hi, {{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-icon" type="submit" title="Log out" aria-label="Log out"><svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M18 12H9" />
                    </svg></button></form>
        </div>
    </nav>
    <header>
        <div class="shop-header-main">
            <div class="shop-title">
                <h1>Menu</h1><a class="menu-reserve" data-menu-reserve href="{{ route('reservations.create') }}">Reserve</a>
            </div>
            <div class="shop-search"><button type="button" aria-label="Search products"><span></span></button><input id="shop-search" type="search" placeholder="Search products"></div>
        </div>
        <div class="shop-category-row"><button class="category-arrow" type="button" data-shop-scroll="-1" aria-label="Scroll categories left">‹</button>
            <div class="shop-category-tabs"><button class="active" type="button" data-shop-category="all">All</button>@foreach($products->pluck('category')->unique()->values() as $category)<button type="button" data-shop-category="{{ $category }}">{{ $category }}</button>@endforeach</div><button class="category-arrow" type="button" data-shop-scroll="1" aria-label="Scroll categories right">›</button>
        </div>
    </header>
    @if($errors->any())<div class="error shop-error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('shop.orders.store') }}" enctype="multipart/form-data" data-shop-order-form>@csrf
        <div class="customer-order-layout">
            <section>
                <div class="shop-grid">
                    @forelse($products->groupBy('category') as $category => $items)
                    @foreach($items as $product)
                    <article data-shop-card data-name="{{ $product->name }}" data-category="{{ $product->category }}" data-price="{{ $product->price }}">
                        @if($imageUrl = $product->imageUrl())<img src="{{ $imageUrl }}" alt="{{ $product->name }}">@else<div class="shop-placeholder">{{ strtoupper(substr($product->name,0,1)) }}</div>@endif
                        <div>
                            <h3>{{ $product->name }}</h3>
                            <p>{{ $product->description }}</p>
                            <div class="shop-price"><strong>&#8369;{{ number_format($product->price,2) }}</strong><span>{{ $product->stock }} available</span></div><input class="shop-quantity" name="quantities[{{ $product->id }}]" type="hidden" min="0" max="{{ $product->stock }}" value="{{ old('quantities.'.$product->id,0) }}"><button class="shop-add bi bi-cart" type="button" aria-label="Add {{ $product->name }}"></button>
                        </div>
                    </article>
                    @endforeach
                    @empty
                    <p>No menu items are available.</p>
                    @endforelse
                </div>
            </section>
            <aside class="customer-cart">
                <h2>Current Order</h2>
                <div class="customer-cart-name">{{ auth()->user()->name }}</div>
                <div id="customer-cart-items" class="customer-cart-items">
                    <p>No items selected.</p>
                </div>
                <div class="customer-cart-total"><span>Total</span><strong id="customer-cart-total">&#8369;0.00</strong></div>
                <p class="cart-error" data-cart-error role="alert" hidden></p><button type="button" data-checkout-open>Place Order</button>
            </aside>
        </div>
        <div class="order-bar">
            <div><strong>Ready to order?</strong><small>Place the order, reserve your table, then choose payment.</small></div><button type="button" data-checkout-open>Place Order <span>&rarr;</span></button>
        </div>

        <dialog class="checkout-modal" data-checkout-modal aria-labelledby="checkout-title">
            <section class="checkout-panel">
                <div class="checkout-head">
                    <div>
                        <p>ORDER CHECKOUT</p>
                        <h2 id="checkout-title">Complete your visit</h2><span>Reserve a table first. Payment comes last.</span>
                    </div>
                    <button type="button" aria-label="Close checkout" data-checkout-close>&times;</button>
                </div>

                <ol class="checkout-progress" aria-label="Checkout progress">
                    <li class="complete"><span>1</span>Order</li>
                    <li data-progress-step="reservation"><span>2</span>Reservation</li>
                    <li data-progress-step="payment"><span>3</span>Payment</li>
                    <li><span>4</span>Receipt</li>
                </ol>

                <section class="checkout-step" data-checkout-step="reservation">
                    <div class="checkout-step-title">
                        <p>STEP 2 OF 4</p>
                        <h3>Reserve a table</h3><span>Your selected food is already in the order, so only the table details are needed here.</span>
                    </div>

                    <div class="checkout-order-summary">
                        <div><strong>Your order</strong><b data-modal-order-total>&#8369;0.00</b></div>
                        <div class="checkout-order-lines" data-modal-order-lines></div>
                    </div>

                    <div class="checkout-fields">
                        <div class="field"><label for="checkout_name">Full name</label><input class="control" id="checkout_name" value="{{ auth()->user()->name }}" readonly></div>
                        <div class="field"><label for="checkout_phone">Phone number</label><input class="control" id="checkout_phone" name="phone" type="tel" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" placeholder="09XXXXXXXXX" value="{{ old('phone', auth()->user()->phone) }}" required><small>11 digits starting with 09</small></div>
                        <div class="field"><label for="checkout_table_size">Table size</label><select class="control" id="checkout_table_size" name="table_size" required>@foreach($tableFees as $size => $fee)<option value="{{ $size }}" data-fee="{{ $fee }}" @selected((int) old('table_size', 2)===$size)>{{ $size }} {{ $size === 1 ? 'seat' : 'seats' }} &middot; &#8369;{{ number_format($fee, 2) }}</option>@endforeach</select></div>
                        <div class="field"><label for="checkout_reservation_at">Date and time</label><input class="control" id="checkout_reservation_at" name="reservation_at" type="datetime-local" min="{{ now()->addHour()->format('Y-m-d\TH:i') }}" value="{{ old('reservation_at') }}" required></div>
                        <div class="field full"><label for="checkout_notes">Additional notes (optional)</label><textarea class="control" id="checkout_notes" name="notes" rows="3" placeholder="Accessibility needs, seating preferences, or other notes">{{ old('notes') }}</textarea></div>
                    </div>

                    <div class="checkout-actions"><button type="button" class="checkout-secondary" data-checkout-close>Back to menu</button><button type="button" class="checkout-primary" data-reservation-submit>Submit reservation <span>&rarr;</span></button></div>
                </section>

                <section class="checkout-step" data-checkout-step="payment" hidden>
                    <div class="checkout-step-title">
                        <p>STEP 3 OF 4</p>
                        <h3>Payment</h3><span>Choose how you will pay, then continue directly to your receipt.</span>
                    </div>

                    <div class="payment-total-card">
                        <div><span>Food order</span><strong data-payment-order-total>&#8369;0.00</strong></div>
                        <div><span>Table reservation</span><strong data-payment-reservation-fee>&#8369;0.00</strong></div>
                        <div><b>Total due</b><b data-payment-total>&#8369;0.00</b></div>
                    </div>

                    <fieldset class="checkout-payment-fieldset">
                        <legend>Payment method</legend>
                        <div class="checkout-options">
                            <label><input type="radio" name="payment_method" value="cash" @checked(old('payment_method','cash')==='cash' )><span><strong>Walk In Pay</strong><small>Pay the total at the counter when you arrive.</small></span></label>
                            <label><input type="radio" name="payment_method" value="gcash" @checked(old('payment_method')==='gcash' )><span><strong>GCash</strong><small>Scan the QR and submit your payment details.</small></span></label>
                        </div>
                    </fieldset>

                    <div class="gcash-checkout" data-gcash-fields>
                        <div class="shop-qr"><img src="{{ $gcashQrPath ? route('public.media', ['path' => $gcashQrPath]) : asset('gcash-qr-placeholder.svg') }}" alt="Kermit's GCash QR code"><small>{{ $gcashQrPath ? 'Scan using your GCash app.' : 'The Super Admin has not uploaded the GCash QR yet.' }}</small></div>
                        <div class="gcash-details">
                            <div class="field"><label for="payment_reference">GCash transaction reference</label><input class="control" id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}" inputmode="numeric" pattern="[0-9]{13}" minlength="13" maxlength="13" placeholder="Enter exactly 13 digits"><small>Use the 13-digit reference shown by GCash.</small></div>
                            <div class="field"><label for="payment_proof">Payment proof</label><input class="control" id="payment_proof" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp"><small>Upload a JPG, PNG, or WebP image up to 5 MB.</small></div>
                        </div>
                    </div>
                    <div class="checkout-note" data-payment-note aria-live="polite"></div>
                    <div class="checkout-actions"><button type="button" class="checkout-secondary" data-payment-back>&larr; Reservation</button><button class="checkout-primary" type="submit">Confirm payment &amp; view receipt <span>&rarr;</span></button></div>
                </section>
            </section>
        </dialog>
    </form>
</main>
<style>
    .customer-shop {
        min-height: 100dvh;
        background: #f4f5ee;
        padding-bottom: 110px
    }

    .customer-shop nav {
        height: 78px;
        width: min(1180px, calc(100% - 30px));
        margin: auto;
        display: flex;
        align-items: center;
        justify-content: space-between
    }

    .customer-shop nav>a {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none
    }

    .customer-shop nav img {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: contain;
        background: #fff
    }

    .customer-shop nav>a strong {
        letter-spacing: .1em
    }

    .customer-actions {
        display: flex;
        align-items: center;
        gap: 8px
    }

    .customer-actions a {
        background: #171817;
        color: #fff;
        text-decoration: none;
        border-radius: 9px;
        padding: 9px 12px;
        font-size: 13px;
        font-weight: 700
    }

    .customer-actions a:first-child {
        background: #fff;
        color: #171817;
        border: 1px solid #ccd0c5
    }

    .customer-actions form {
        margin: 0
    }

    .customer-actions button {
        border: 1px solid #ccd0c5;
        border-radius: 9px;
        background: #fff;
        padding: 9px 12px
    }

    .customer-shop header,
    .customer-shop>form,
    .shop-error {
        width: min(1180px, calc(100% - 30px));
        margin-inline: auto
    }

    .customer-shop header {
        padding: 50px 0 30px
    }

    .customer-shop header p {
        font-size: 12px;
        letter-spacing: .16em;
        color: #7a8300
    }

    .customer-shop header h1 {
        font-family: Georgia, serif;
        font-size: 48px;
        font-weight: 500;
        margin: 8px 0
    }

    .customer-shop header>span {
        color: #687286
    }

    .shop-error {
        background: #fff0f0;
        padding: 12px;
        border-radius: 9px
    }

    .shop-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px
    }

    .menu-category {
        grid-column: 1/-1;
        font-family: Georgia, serif;
        font-size: 28px;
        margin: 28px 0 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #171817
    }

    .shop-grid article {
        background: #fff;
        border: 1px solid #daddd1;
        border-radius: 17px;
        overflow: hidden
    }

    .shop-grid article>img,
    .shop-placeholder {
        width: 100%;
        height: 210px;
        object-fit: cover
    }

    .shop-placeholder {
        display: grid;
        place-items: center;
        background: #e9ecd4;
        color: #747d00;
        font-size: 54px
    }

    .shop-grid article>div {
        padding: 19px
    }

    .shop-grid h3 {
        font-size: 19px;
        margin: 0 0 7px
    }

    .shop-grid p {
        color: #687286;
        height: 39px;
        overflow: hidden;
        margin: 0 0 15px
    }

    .shop-price {
        display: flex;
        justify-content: space-between;
        margin-bottom: 14px
    }

    .shop-price span {
        font-size: 12px;
        color: #687286
    }

    .shop-grid input {
        width: 80px;
        border: 1px solid #d7dce5;
        border-radius: 9px;
        padding: 9px
    }

    .order-bar {
        position: fixed;
        left: 50%;
        bottom: 14px;
        transform: translateX(-50%);
        width: min(760px, calc(100% - 28px));
        background: #171817;
        color: #fff;
        border-radius: 16px;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 20px 50px #0005
    }

    .order-bar div {
        display: grid
    }

    .order-bar small {
        color: #aaada6;
        margin-top: 3px
    }

    .order-bar button {
        border: 0;
        border-radius: 10px;
        background: #b5c019;
        padding: 12px 16px;
        font-weight: 700
    }

    @media(max-width:900px) {
        .shop-grid {
            grid-template-columns: repeat(2, 1fr)
        }
    }

    @media(max-width:650px) {

        .customer-actions>span,
        .customer-shop nav>a strong {
            display: none
        }

        .customer-actions a {
            padding: 8px;
            font-size: 12px
        }
    }

    @media(max-width:580px) {
        .customer-shop header {
            padding-top: 30px
        }

        .customer-shop header h1 {
            font-size: 37px
        }

        .shop-grid {
            grid-template-columns: 1fr
        }

        .order-bar div {
            display: none
        }

        .order-bar button {
            width: 100%
        }
    }

    .checkout-modal[hidden] {
        display: none
    }

    .checkout-modal {
        position: fixed;
        inset: 0;
        z-index: 100;
        display: grid;
        grid-template-columns: 1fr minmax(340px, 480px)
    }

    .checkout-backdrop {
        grid-column: 1/-1;
        grid-row: 1;
        border: 0;
        background: rgba(12, 13, 12, .62);
        backdrop-filter: blur(4px)
    }

    .checkout-panel {
        grid-column: 2;
        grid-row: 1;
        z-index: 1;
        height: 100dvh;
        overflow-y: auto;
        background: #f8f7f1;
        padding: 32px;
        box-shadow: -25px 0 60px #0004;
        scrollbar-width: none
    }

    .checkout-panel::-webkit-scrollbar {
        display: none
    }

    .checkout-panel>header {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 26px
    }

    .checkout-panel>header p {
        margin: 0 0 7px;
        color: #7b8500;
        font-size: 11px;
        font-weight: 850;
        letter-spacing: .14em
    }

    .checkout-panel>header h2 {
        margin: 0;
        font-size: 28px
    }

    .checkout-panel>header span {
        display: block;
        color: #737970;
        margin-top: 7px;
        line-height: 1.5
    }

    .checkout-panel>header button {
        width: 40px;
        height: 40px;
        border: 1px solid #d2d5cb;
        border-radius: 10px;
        background: #fff;
        font-size: 24px
    }

    .checkout-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px
    }

    .checkout-options>label {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 15px;
        border: 1px solid #d5d8ce;
        border-radius: 13px;
        background: #fff;
        cursor: pointer
    }

    .checkout-options>label:has(input:checked) {
        border-color: #9da708;
        background: #f6f8df;
        box-shadow: 0 0 0 2px rgba(174, 187, 25, .13)
    }

    .checkout-options input {
        margin-top: 4px
    }

    .checkout-options span {
        display: grid
    }

    .checkout-options small {
        color: #72776f;
        line-height: 1.45;
        margin-top: 4px
    }

    .gcash-checkout {
        display: grid;
        grid-template-columns: 150px 1fr;
        gap: 18px;
        align-items: center;
        margin-top: 18px;
        padding: 18px;
        border-radius: 14px;
        background: #eef3e8
    }

    .gcash-checkout[hidden] {
        display: none
    }

    .shop-qr {
        text-align: center
    }

    .shop-qr img {
        width: 140px;
        aspect-ratio: 1;
        object-fit: contain;
        background: #fff;
        border: 1px solid #d5d8ce;
        border-radius: 12px;
        padding: 8px
    }

    .shop-qr small {
        display: block;
        color: #676d64;
        font-size: 10px;
        line-height: 1.4;
        margin-top: 6px
    }

    .gcash-checkout .field {
        margin: 0
    }

    .gcash-checkout .field>label {
        display: block;
        font-weight: 800;
        margin-bottom: 7px
    }

    .gcash-checkout .control {
        width: 100%;
        font-size: 17px
    }

    .gcash-checkout .field small {
        display: block;
        color: #6f756c;
        line-height: 1.4;
        margin-top: 7px
    }

    .checkout-note {
        margin: 18px 0;
        padding: 13px 15px;
        border-radius: 11px;
        background: #f0f1eb;
        color: #555c52;
        font-size: 13px;
        line-height: 1.5
    }

    .place-order-button {
        width: 100%;
        min-height: 52px;
        border: 0;
        border-radius: 12px;
        background: #171817;
        color: #fff;
        padding: 14px 17px;
        display: flex;
        justify-content: space-between;
        font-weight: 800;
        cursor: pointer
    }

    .place-order-button:hover {
        background: #30322e
    }

    @media(max-width:650px) {
        .checkout-modal {
            grid-template-columns: 1fr
        }

        .checkout-panel {
            grid-column: 1;
            padding: 22px 16px
        }

        .checkout-options {
            grid-template-columns: 1fr
        }

        .gcash-checkout {
            grid-template-columns: 1fr
        }

        .shop-qr img {
            width: min(220px, 100%)
        }
    }

    /* Full-page customer shop */
    @media(min-width:901px) {
        .customer-shop {
            display: grid;
            grid-template-columns: 250px minmax(0, 1fr);
            grid-template-rows: auto 1fr;
            align-items: start;
            min-height: 100dvh;
            padding: 0 0 110px 0 !important;
            background: #f5f4ed !important
        }

        .customer-shop>nav {
            position: fixed !important;
            inset: 0 auto 0 0;
            width: 250px !important;
            height: 100dvh !important;
            padding: 30px 20px !important;
            background: linear-gradient(160deg, #151615, #21231f) !important;
            border: 0 !important;
            display: flex !important;
            flex-direction: column;
            align-items: stretch !important;
            justify-content: flex-start !important;
            color: #fff;
            backdrop-filter: none !important
        }

        .customer-shop>nav>a {
            padding: 4px 8px 28px;
            border-bottom: 1px solid #343630
        }

        .customer-shop>nav img {
            width: 58px;
            height: 58px
        }

        .customer-shop>nav>a strong {
            color: #fff
        }

        .customer-shop .customer-actions {
            display: grid !important;
            grid-template-rows: auto auto auto 1fr auto auto;
            align-items: stretch !important;
            gap: 8px !important;
            margin-top: 28px;
            min-height: 0;
            flex: 1
        }

        .customer-shop .customer-actions>a {
            min-height: 46px;
            display: flex;
            align-items: center;
            border: 0 !important;
            background: #292b27 !important;
            color: #eee !important;
            padding: 12px 14px !important
        }

        .customer-shop .customer-actions>a:hover {
            background: #363933 !important
        }

        .customer-shop .customer-actions>a.active {
            background: #34372f !important;
            box-shadow: inset 4px 0 #b5c019
        }

        .customer-shop .customer-actions>span {
            grid-row: 5;
            margin-top: 0;
            padding: 15px 12px 4px;
            color: #aeb2a9;
            font-size: 13px;
            border-top: 1px solid #343630
        }

        .customer-shop .customer-actions form {
            grid-row: 6;
            margin-top: 0
        }

        .customer-shop .customer-actions button {
            width: 100%;
            min-height: 44px;
            background: transparent !important;
            color: #fff;
            border-color: #464941 !important
        }

        .customer-shop>header,
        .customer-shop>form,
        .customer-shop>.shop-error {
            grid-column: 2;
            width: auto !important;
            margin-inline: 0 !important
        }

        .customer-shop>header {
            padding: 38px clamp(26px, 4vw, 58px) 24px !important;
            background: #f5f4ed
        }

        .customer-shop>header h1 {
            font-size: clamp(38px, 4vw, 56px)
        }

        .customer-shop>form {
            padding: 0 clamp(26px, 4vw, 58px)
        }

        .customer-shop>.shop-error {
            margin: 0 clamp(26px, 4vw, 58px) 18px !important
        }

        .customer-shop .shop-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px
        }

        .customer-shop .menu-category {
            margin-top: 34px
        }

        .customer-shop .order-bar {
            left: calc(250px + (100vw - 250px)/2) !important;
            width: min(780px, calc(100vw - 290px)) !important
        }
    }

    @media(min-width:901px) and (max-width:1220px) {
        .customer-shop .shop-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }
    }

    @media(max-width:900px) {
        .customer-shop {
            display: block;
            padding-bottom: 100px !important
        }

        .customer-shop>nav {
            position: sticky !important
        }

        .customer-shop .shop-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .customer-shop>header {
            padding-top: 32px !important
        }

        .customer-shop .order-bar {
            left: 50% !important
        }
    }

    @media(max-width:580px) {
        .customer-shop .shop-grid {
            grid-template-columns: 1fr
        }

        .customer-shop>nav {
            height: 68px !important
        }

        .customer-shop>nav img {
            width: 42px;
            height: 42px
        }

        .customer-shop .customer-actions>span {
            display: none
        }

        .customer-shop>header {
            padding-top: 24px !important
        }

        .customer-shop .menu-category {
            font-size: 25px;
            margin-top: 24px
        }

        .customer-shop .order-bar {
            width: calc(100% - 16px) !important
        }
    }

    @media(min-width:901px) {
        .customer-shop .customer-actions {
            grid-template-columns: minmax(0, 1fr) 42px !important;
            grid-template-rows: auto auto auto 1fr auto !important
        }

        .customer-shop .customer-actions>a {
            grid-column: 1/-1
        }

        .customer-shop .customer-actions>span {
            grid-row: 5 !important;
            grid-column: 1;
            margin: 0 !important;
            padding: 15px 8px 0 12px !important;
            display: flex;
            align-items: center;
            min-height: 58px
        }

        .customer-shop .customer-actions>form {
            grid-row: 5 !important;
            grid-column: 2;
            margin: 0 !important;
            padding-top: 15px;
            border-top: 1px solid #343630;
            display: flex !important;
            align-items: center;
            justify-content: flex-end
        }

        .customer-shop .customer-actions .logout-icon {
            width: 42px !important;
            height: 42px !important;
            min-width: 42px !important;
            min-height: 42px !important
        }
    }

    .customer-shop {
        background: #efefef !important
    }

    .customer-shop>header {
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        gap: 20px
    }

    .customer-shop>header p {
        margin: 0 0 12px !important;
        color: #4b16e8 !important;
        font-weight: 900 !important;
        letter-spacing: 0 !important
    }

    .customer-shop>header h1 {
        font-family: inherit !important;
        font-size: 30px !important;
        font-weight: 900 !important
    }

    .shop-search {
        min-width: 280px;
        width: min(360px, 45%);
        height: 46px;
        display: flex;
        align-items: center;
        gap: 10px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 12px;
        padding: 0 14px
    }

    .shop-search span {
        font-size: 25px;
        color: #555
    }

    .shop-search input {
        width: 100%;
        border: 0;
        outline: 0;
        background: transparent;
        font: inherit
    }

    .customer-order-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 278px;
        gap: 28px;
        align-items: start
    }

    .shop-category-tabs {
        display: flex;
        gap: 22px;
        overflow-x: auto;
        margin-bottom: 20px
    }

    .shop-category-tabs button {
        min-width: 92px;
        height: 36px;
        border: 0;
        border-radius: 11px;
        background: #fff;
        color: #171817;
        font-weight: 800
    }

    .shop-category-tabs button.active {
        background: #202124;
        color: #fff
    }

    .customer-shop .shop-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 18px !important
    }

    .customer-shop .menu-category {
        font-family: inherit !important;
        font-size: 22px !important;
        border: 0 !important;
        margin: 8px 0 0 !important
    }

    .shop-grid article {
        border: 0 !important;
        border-radius: 12px !important
    }

    .shop-grid article>img,
    .shop-placeholder {
        height: 128px !important;
        margin: 12px 12px 0;
        width: calc(100% - 24px) !important;
        border-radius: 8px
    }

    .shop-grid article>div {
        position: relative;
        padding: 12px !important
    }

    .shop-grid h3 {
        font-size: 15px !important;
        margin-bottom: 18px !important
    }

    .shop-grid p,
    .shop-price span {
        display: none !important
    }

    .shop-price {
        margin: 0 !important
    }

    .shop-price strong {
        font-size: 16px !important
    }

    .shop-add {
        position: absolute;
        right: 12px;
        bottom: 10px;
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 50%;
        background: #202124;
        color: #fff;
        font-size: 24px;
        line-height: 1;
        display: grid;
        place-items: center
    }

    .customer-cart {
        position: sticky;
        top: 24px;
        background: #fff;
        border-radius: 12px;
        padding: 18px;
        min-height: 520px
    }

    .customer-cart h2 {
        font-size: 21px;
        margin: 0 0 18px
    }

    .customer-cart-name {
        font-weight: 800;
        margin-bottom: 22px
    }

    .customer-cart-items {
        display: grid;
        gap: 12px;
        min-height: 230px
    }

    .customer-cart-items>p {
        color: #777b72
    }

    .customer-cart-line {
        display: grid;
        grid-template-columns: 58px 1fr;
        gap: 12px;
        align-items: center
    }

    .customer-cart-line img,
    .customer-cart-line .thumb {
        width: 58px;
        height: 58px;
        border-radius: 10px;
        object-fit: cover;
        background: #f0f1ed;
        display: grid;
        place-items: center
    }

    .customer-cart-line h3 {
        font-size: 14px;
        margin: 0 0 8px
    }

    .customer-cart-controls {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px
    }

    .customer-cart-controls button {
        width: 24px;
        height: 24px;
        border: 0;
        border-radius: 50%;
        background: #70747a;
        color: #fff;
        font-weight: 900
    }

    .customer-cart-total {
        margin-top: 22px;
        background: #f2f2f2;
        border-radius: 12px;
        padding: 14px;
        display: flex;
        justify-content: space-between;
        font-weight: 900
    }

    .customer-cart>button {
        width: 100%;
        min-height: 42px;
        border: 0;
        border-radius: 999px;
        background: #5800f0;
        color: #fff;
        font-weight: 900;
        margin-top: 16px
    }

    .order-bar {
        display: none !important
    }

    @media(max-width:1160px) {
        .customer-order-layout {
            grid-template-columns: 1fr
        }

        .customer-cart {
            position: static;
            min-height: 0;
            order: -1
        }

        .customer-shop .shop-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important
        }
    }

    @media(max-width:650px) {
        .customer-shop>header {
            display: grid !important
        }

        .shop-search {
            width: 100%;
            min-width: 0
        }

        .customer-shop .shop-grid {
            grid-template-columns: 1fr !important
        }

        .shop-category-tabs {
            gap: 10px
        }

        .shop-category-tabs button {
            min-width: 84px
        }
    }

    .customer-shop>header {
        display: grid !important;
        gap: 16px !important;
        align-items: start !important
    }

    .shop-header-main {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px
    }

    .shop-category-tabs {
        margin: 0 !important;
        padding-bottom: 0 !important;
        scrollbar-width: none !important;
        -ms-overflow-style: none !important
    }

    .shop-category-tabs::-webkit-scrollbar,
    .customer-shop *::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important
    }

    .customer-shop,
    .customer-shop * {
        scrollbar-width: none !important
    }

    .shop-grid p {
        display: block !important;
        height: auto !important;
        min-height: 34px !important;
        max-height: 46px !important;
        margin: 0 0 12px !important;
        overflow: hidden !important;
        color: #6d746b !important;
        font-size: 12px !important;
        line-height: 1.35 !important
    }

    .shop-grid h3 {
        margin-bottom: 6px !important
    }

    .shop-price span {
        display: block !important;
        font-size: 11px !important
    }

    .shop-grid article {
        min-height: 250px !important
    }

    @media(max-width:650px) {
        .shop-header-main {
            display: grid
        }

        .shop-search {
            width: 100%;
            min-width: 0
        }
    }

    .shop-search {
        width: min(450px, 42vw) !important;
        min-width: 320px !important;
        height: 58px !important;
        border: 1px solid #d7d8d2 !important;
        border-radius: 14px !important;
        background: #fff !important;
        padding: 0 18px !important;
        box-shadow: 0 1px 0 rgba(0, 0, 0, .03) !important
    }

    .shop-search span {
        width: 24px;
        height: 24px;
        display: grid;
        place-items: center;
        font-size: 28px !important;
        line-height: 1;
        color: #445064 !important
    }

    .shop-search input {
        height: 100% !important;
        font-weight: 750 !important;
        color: #232323 !important
    }

    .shop-search input::placeholder {
        color: #69707a !important
    }

    .shop-category-row {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) 42px;
        gap: 10px;
        align-items: center
    }

    .category-arrow {
        width: 42px;
        height: 42px;
        border: 0;
        border-radius: 50%;
        background: #fff;
        color: #1d1f22;
        font-size: 32px;
        font-weight: 900;
        line-height: 1;
        box-shadow: 0 1px 0 rgba(0, 0, 0, .05)
    }

    .shop-category-tabs {
        display: flex !important;
        gap: 26px !important;
        overflow-x: auto !important;
        scroll-behavior: smooth !important;
        overscroll-behavior-inline: contain
    }

    .shop-category-tabs button {
        flex: 0 0 auto !important;
        height: 44px !important;
        min-width: 116px !important;
        padding: 0 22px !important;
        font-size: 17px !important;
        line-height: 1.1 !important;
        white-space: normal !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important
    }

    @media(max-width:650px) {
        .shop-search {
            width: 100% !important;
            min-width: 0 !important
        }

        .shop-category-row {
            grid-template-columns: 36px minmax(0, 1fr) 36px
        }

        .category-arrow {
            width: 36px;
            height: 36px
        }

        .shop-category-tabs {
            gap: 12px !important
        }

        .shop-category-tabs button {
            min-width: 96px !important;
            font-size: 14px !important
        }
    }

    .shop-header-main h1 {
        margin-top: 0 !important
    }

    .shop-search {
        width: min(420px, 40vw) !important;
        min-width: 300px !important;
        height: 52px !important;
        border-color: #d9ddd2 !important;
        border-radius: 16px !important;
        padding: 0 16px !important;
        gap: 13px !important;
        box-shadow: 0 10px 24px rgba(23, 24, 23, .06) !important
    }

    .shop-search span {
        position: relative !important;
        width: 22px !important;
        height: 22px !important;
        display: block !important;
        font-size: 0 !important;
        flex: 0 0 22px !important;
        color: transparent !important
    }

    .shop-search span:before {
        content: "";
        position: absolute;
        left: 1px;
        top: 1px;
        width: 14px;
        height: 14px;
        border: 2px solid #4d5b6b;
        border-radius: 50%;
        box-sizing: border-box
    }

    .shop-search span:after {
        content: "";
        position: absolute;
        right: 1px;
        bottom: 3px;
        width: 8px;
        height: 2px;
        background: #4d5b6b;
        border-radius: 2px;
        transform: rotate(45deg);
        transform-origin: center
    }

    .shop-search input {
        font-size: 15px !important;
        font-weight: 750 !important
    }

    .shop-search:focus-within {
        border-color: #a7b000 !important;
        box-shadow: 0 0 0 3px rgba(199, 211, 0, .16), 0 10px 24px rgba(23, 24, 23, .06) !important
    }

    @media(max-width:650px) {
        .shop-search {
            min-width: 0 !important;
            width: 100% !important
        }
    }

    .shop-search {
        width: min(380px, 42vw) !important;
        min-width: 280px !important;
        height: 46px !important;
        border: 1px solid #d4d6cf !important;
        border-radius: 10px !important;
        background: #fff !important;
        padding: 0 14px !important;
        gap: 10px !important;
        box-shadow: none !important
    }

    .shop-search span {
        width: 18px !important;
        height: 18px !important;
        flex-basis: 18px !important
    }

    .shop-search span:before {
        width: 12px !important;
        height: 12px !important;
        border-width: 2px !important;
        border-color: #5f6872 !important
    }

    .shop-search span:after {
        right: 0 !important;
        bottom: 2px !important;
        width: 7px !important;
        background: #5f6872 !important
    }

    .shop-search input {
        font-size: 14px !important;
        font-weight: 650 !important
    }

    .shop-search:focus-within {
        border-color: #202124 !important;
        box-shadow: none !important
    }

    @media(max-width:650px) {
        .shop-search {
            min-width: 0 !important;
            width: 100% !important
        }
    }

    .shop-search {
        width: min(520px, 44vw) !important;
        min-width: 320px !important;
        height: 58px !important;
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        border: 0 !important;
        border-radius: 999px !important;
        background: #fff !important;
        padding: 0 22px !important;
        box-shadow: 0 14px 28px rgba(33, 45, 77, .12) !important
    }

    .shop-search button {
        width: 38px !important;
        height: 38px !important;
        border: 0 !important;
        background: transparent !important;
        padding: 0 !important;
        display: grid !important;
        place-items: center !important;
        cursor: pointer !important;
        flex: 0 0 38px !important
    }

    .shop-search button span {
        position: relative !important;
        width: 28px !important;
        height: 28px !important;
        display: block !important;
        font-size: 0 !important;
        color: transparent !important
    }

    .shop-search button span:before {
        content: "";
        position: absolute;
        left: 2px;
        top: 2px;
        width: 18px !important;
        height: 18px !important;
        border: 4px solid #5e5968 !important;
        border-radius: 50% !important;
        box-sizing: border-box !important
    }

    .shop-search button span:after {
        content: "";
        position: absolute;
        right: 2px;
        bottom: 3px;
        width: 12px !important;
        height: 4px !important;
        background: #5e5968 !important;
        border-radius: 4px !important;
        transform: rotate(45deg) !important;
        transform-origin: center !important
    }

    .shop-search input {
        width: 100% !important;
        height: 100% !important;
        border: 0 !important;
        outline: 0 !important;
        background: transparent !important;
        font-size: 15px !important;
        font-weight: 650 !important;
        color: #232323 !important
    }

    .shop-search input::placeholder {
        color: #777b84 !important
    }

    .shop-search:focus-within {
        box-shadow: 0 0 0 3px rgba(199, 211, 0, .18), 0 14px 28px rgba(33, 45, 77, .12) !important
    }

    @media(max-width:650px) {
        .shop-search {
            min-width: 0 !important;
            width: 100% !important;
            height: 54px !important;
            padding: 0 18px !important
        }
    }

    .shop-grid article>div {
        padding-bottom: 18px !important
    }

    .shop-price {
        display: grid !important;
        grid-template-columns: 1fr auto !important;
        align-items: end !important;
        gap: 14px !important;
        margin: 0 !important;
        padding-right: 48px !important
    }

    .shop-price strong {
        white-space: nowrap !important
    }

    .shop-price span {
        display: block !important;
        min-width: max-content !important;
        color: #596273 !important;
        font-size: 12px !important;
        font-weight: 650 !important;
        line-height: 1.2 !important;
        text-align: right !important;
        white-space: nowrap !important
    }

    .shop-add {
        right: 14px !important;
        bottom: 12px !important
    }

    @media(max-width:430px) {
        .shop-price {
            padding-right: 46px !important;
            gap: 8px !important
        }

        .shop-price span {
            font-size: 11px !important
        }
    }

    .customer-app-link b {
        display: none
    }

    .customer-app-link.disabled {
        opacity: .55;
        cursor: default;
        pointer-events: none
    }

    @media(max-width:900px) {
        .customer-app-link span {
            display: none
        }

        .customer-app-link b {
            display: inline;
            font: inherit
        }
    }

    .shop-title {
        display: flex;
        align-items: center;
        gap: 14px
    }

    .menu-reserve {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 9px 16px;
        border-radius: 10px;
        background: #202124;
        color: #fff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 850
    }

    .menu-reserve:hover {
        background: #36383b
    }

    @media(max-width:650px) {
        .shop-title {
            justify-content: space-between
        }

        .menu-reserve {
            min-height: 38px;
            padding: 8px 14px
        }
    }

    .cart-error {
        margin: 12px 0 0;
        padding: 10px;
        border-radius: 9px;
        background: #fff0f0;
        color: #b42318;
        font-size: 12px;
        font-weight: 700
    }

    .checkout-modal:not([open]) {
        display: none !important
    }

    .checkout-modal[open] {
        display: block
    }

    .checkout-modal {
        inset: 0;
        width: min(760px, calc(100% - 24px));
        height: auto;
        max-height: min(92dvh, 900px);
        margin: auto;
        padding: 0;
        border: 0;
        border-radius: 20px;
        background: #f8f7f1;
        color: #171817;
        overflow: hidden;
        box-shadow: 0 28px 90px rgba(0, 0, 0, .35)
    }

    .checkout-modal::backdrop {
        background: rgba(12, 13, 12, .66);
        backdrop-filter: blur(4px)
    }

    .checkout-panel {
        display: block;
        box-sizing: border-box;
        width: 100%;
        height: auto;
        max-height: min(92dvh, 900px);
        padding: 28px;
        overflow-y: auto;
        background: #f8f7f1;
        box-shadow: none;
        scrollbar-width: thin
    }

    .checkout-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        padding-bottom: 20px;
        border-bottom: 1px solid #dfe1d8
    }

    .checkout-head p,
    .checkout-step-title p {
        margin: 0 0 6px;
        color: #747d00;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .14em
    }

    .checkout-head h2 {
        margin: 0;
        font-size: 27px;
        letter-spacing: -.03em
    }

    .checkout-head span,
    .checkout-step-title>span {
        display: block;
        margin-top: 6px;
        color: #6e746b;
        font-size: 13px;
        line-height: 1.45
    }

    .checkout-head>button {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        border: 1px solid #d2d5cb;
        border-radius: 10px;
        background: #fff;
        font-size: 24px;
        cursor: pointer
    }

    .checkout-progress {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        margin: 20px 0 24px;
        padding: 0;
        list-style: none
    }

    .checkout-progress li {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #858a81;
        font-size: 11px;
        font-weight: 800
    }

    .checkout-progress li span {
        width: 25px;
        height: 25px;
        display: grid;
        place-items: center;
        border: 1px solid #d3d6cc;
        border-radius: 50%;
        background: #fff
    }

    .checkout-progress li.active {
        color: #171817
    }

    .checkout-progress li.active span {
        border-color: #171817;
        background: #171817;
        color: #fff
    }

    .checkout-progress li.complete {
        color: #687100
    }

    .checkout-progress li.complete span {
        border-color: #c7d300;
        background: #e9edb9;
        color: #5f6700
    }

    .checkout-step[hidden] {
        display: none !important
    }

    .checkout-step-title {
        margin-bottom: 18px
    }

    .checkout-step-title h3 {
        margin: 0;
        font-size: 24px;
        letter-spacing: -.03em
    }

    .checkout-order-summary,
    .payment-total-card {
        margin-bottom: 20px;
        padding: 16px;
        border: 1px solid #dde0d6;
        border-radius: 14px;
        background: #fff
    }

    .checkout-order-summary>div:first-child,
    .payment-total-card>div {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px
    }

    .checkout-order-summary>div:first-child {
        padding-bottom: 10px;
        border-bottom: 1px solid #eceee8
    }

    .checkout-order-lines {
        display: grid;
        gap: 7px;
        padding-top: 10px
    }

    .checkout-order-lines>div {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        color: #61675f;
        font-size: 12px
    }

    .checkout-order-lines>div strong {
        color: #252724
    }

    .checkout-fields {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px
    }

    .checkout-fields .field {
        margin: 0
    }

    .checkout-fields .full {
        grid-column: 1/-1
    }

    .checkout-fields label,
    .gcash-details label {
        display: block;
        margin-bottom: 6px;
        font-size: 12px;
        font-weight: 800
    }

    .checkout-fields small,
    .gcash-details small {
        display: block;
        margin-top: 5px;
        color: #737970;
        font-size: 10px
    }

    .checkout-fields .control,
    .gcash-details .control {
        box-sizing: border-box;
        width: 100%
    }

    .checkout-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 22px
    }

    .checkout-actions button {
        min-height: 48px;
        padding: 12px 17px;
        border-radius: 11px;
        font-weight: 850;
        cursor: pointer
    }

    .checkout-secondary {
        border: 1px solid #ccd0c5;
        background: #fff;
        color: #242624
    }

    .checkout-primary {
        min-width: 210px;
        border: 0;
        background: #171817;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 22px
    }

    .checkout-primary:hover {
        background: #30322e
    }

    .payment-total-card {
        display: grid;
        gap: 9px
    }

    .payment-total-card>div {
        color: #666c63;
        font-size: 13px
    }

    .payment-total-card>div:last-child {
        margin-top: 5px;
        padding-top: 12px;
        border-top: 1px solid #dfe2d8;
        color: #171817;
        font-size: 17px
    }

    .checkout-payment-fieldset {
        margin: 0;
        padding: 0;
        border: 0
    }

    .checkout-payment-fieldset legend {
        margin-bottom: 9px;
        font-size: 12px;
        font-weight: 850
    }

    .gcash-details {
        display: grid;
        gap: 13px
    }

    .checkout-note {
        margin: 16px 0 0
    }

    .checkout-options input:disabled+span {
        opacity: .65
    }

    @media(max-width:650px) {
        .checkout-modal {
            width: calc(100% - 12px);
            max-height: 96dvh;
            border-radius: 16px
        }

        .checkout-panel {
            max-height: 96dvh;
            padding: 20px 16px
        }

        .checkout-progress {
            grid-template-columns: repeat(4, max-content);
            justify-content: space-between
        }

        .checkout-progress li {
            display: grid;
            justify-items: center;
            font-size: 9px
        }

        .checkout-fields {
            grid-template-columns: 1fr
        }

        .checkout-fields .full {
            grid-column: auto
        }

        .checkout-actions {
            display: grid;
            grid-template-columns: 1fr
        }

        .checkout-primary {
            min-width: 0
        }

        .checkout-secondary {
            order: 2
        }

        .gcash-checkout {
            grid-template-columns: 1fr
        }

        .shop-qr img {
            width: min(210px, 100%)
        }
    }
</style>

<script>
    (() => {
        const form = document.querySelector('[data-shop-order-form]'),
            dialog = document.querySelector('[data-checkout-modal]');
        if (!form || !dialog) return;

        const cards = [...document.querySelectorAll('[data-shop-card]')],
            openers = [...document.querySelectorAll('[data-checkout-open]')],
            closers = [...dialog.querySelectorAll('[data-checkout-close]')],
            reservationStep = dialog.querySelector('[data-checkout-step="reservation"]'),
            paymentStep = dialog.querySelector('[data-checkout-step="payment"]'),
            reservationSubmit = dialog.querySelector('[data-reservation-submit]'),
            paymentBack = dialog.querySelector('[data-payment-back]'),
            paymentControls = [...paymentStep.querySelectorAll('input,select,textarea')],
            methods = [...paymentStep.querySelectorAll('input[name="payment_method"]')],
            gcash = dialog.querySelector('[data-gcash-fields]'),
            reference = document.getElementById('payment_reference'),
            proof = document.getElementById('payment_proof'),
            note = dialog.querySelector('[data-payment-note]'),
            table = document.getElementById('checkout_table_size'),
            cartError = document.querySelector('[data-cart-error]'),
            modalLines = dialog.querySelector('[data-modal-order-lines]'),
            money = value => new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(value);
        let opener = null;

        const selectedItems = () => cards.map(card => ({
            name: card.dataset.name,
            price: Number(card.dataset.price),
            quantity: Number(card.querySelector('.shop-quantity').value) || 0
        })).filter(item => item.quantity > 0);
        const orderTotal = () => selectedItems().reduce((sum, item) => sum + item.price * item.quantity, 0);
        const reservationFee = () => Number(table.selectedOptions[0]?.dataset.fee || 0);

        function renderSummary() {
            const items = selectedItems();
            modalLines.replaceChildren();
            items.forEach(item => {
                const row = document.createElement('div'),
                    label = document.createElement('span'),
                    amount = document.createElement('strong');
                label.textContent = `${item.quantity} × ${item.name}`;
                amount.textContent = money(item.price * item.quantity);
                row.append(label, amount);
                modalLines.append(row);
            });
            dialog.querySelector('[data-modal-order-total]').textContent = money(orderTotal());
            dialog.querySelector('[data-payment-order-total]').textContent = money(orderTotal());
            dialog.querySelector('[data-payment-reservation-fee]').textContent = money(reservationFee());
            dialog.querySelector('[data-payment-total]').textContent = money(orderTotal() + reservationFee());
        }

        function syncPayment() {
            const isPaymentStep = !paymentStep.hidden,
                isGcash = form.querySelector('input[name="payment_method"]:checked')?.value === 'gcash';
            gcash.hidden = !isGcash;
            reference.disabled = !isPaymentStep || !isGcash;
            proof.disabled = !isPaymentStep || !isGcash;
            reference.required = isPaymentStep && isGcash;
            proof.required = isPaymentStep && isGcash;
            note.textContent = isGcash ? 'Your GCash details will remain pending until they are verified.' : 'Bring payment to the counter when you arrive for your reservation.';
        }

        function setStep(step) {
            const payment = step === 'payment';
            reservationStep.hidden = payment;
            paymentStep.hidden = !payment;
            paymentControls.forEach(control => control.disabled = !payment);
            dialog.querySelectorAll('[data-progress-step]').forEach(item => item.classList.toggle('active', item.dataset.progressStep === step));
            dialog.querySelector('[data-progress-step="reservation"]').classList.toggle('complete', payment);
            renderSummary();
            syncPayment();
            requestAnimationFrame(() => dialog.querySelector(payment ? 'input[name="payment_method"]:checked' : '#checkout_phone')?.focus());
        }

        function openCheckout(step = 'reservation', force = false) {
            if (!force && selectedItems().length === 0) {
                cartError.textContent = 'Select at least one menu item before placing your order.';
                cartError.hidden = false;
                return;
            }
            cartError.hidden = true;
            opener = document.activeElement;
            setStep(step);
            if (!dialog.open) dialog.showModal();
        }

        function closeCheckout() {
            dialog.close()
        }

        openers.forEach(button => button.addEventListener('click', () => openCheckout()));
        closers.forEach(button => button.addEventListener('click', closeCheckout));
        reservationSubmit.addEventListener('click', () => {
            const invalid = [...reservationStep.querySelectorAll('input,select,textarea')].find(control => !control.checkValidity());
            if (invalid) {
                invalid.reportValidity();
                return
            }
            setStep('payment');
        });
        paymentBack.addEventListener('click', () => setStep('reservation'));
        methods.forEach(method => method.addEventListener('change', syncPayment));
        table.addEventListener('change', renderSummary);
        reference.addEventListener('input', () => reference.value = reference.value.replace(/\D/g, '').slice(0, 13));
        document.getElementById('checkout_phone').addEventListener('input', event => event.target.value = event.target.value.replace(/\D/g, '').slice(0, 11));
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeCheckout();
        });
        dialog.addEventListener('close', () => opener?.focus());

        const reopenStep = @json($errors->hasAny(['payment_method', 'payment_reference', 'payment_proof']) ? 'payment' : ($errors->any() ? 'reservation' : null));
        if (reopenStep) requestAnimationFrame(() => openCheckout(reopenStep, true));
        else setStep('reservation');
    })();
</script>









<script>
    (() => {
        const money = n => new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(n),
            cards = [...document.querySelectorAll('[data-shop-card]')],
            box = document.getElementById('customer-cart-items'),
            total = document.getElementById('customer-cart-total'),
            search = document.getElementById('shop-search'),
            buttons = [...document.querySelectorAll('[data-shop-category]')];
        let category = 'all';

        function esc(v) {
            const e = document.createElement('div');
            e.textContent = v;
            return e.innerHTML
        }

        function draw() {
            const selected = cards.map(card => ({
                card,
                input: card.querySelector('.shop-quantity'),
                name: card.dataset.name,
                price: +card.dataset.price,
                img: card.querySelector('img')?.src || '',
                qty: +card.querySelector('.shop-quantity').value || 0
            })).filter(item => item.qty > 0);
            box.innerHTML = selected.length ? selected.map(item => `<div class="customer-cart-line">${item.img?`<img src="${item.img}" alt="">`:`<div class="thumb">${esc(item.name[0]||'')}</div>`}<div><h3>${esc(item.name)}</h3><div class="customer-cart-controls"><strong>${money(item.price)}</strong><button type="button" data-shop-step="-1" data-name="${esc(item.name)}">−</button><b>${item.qty}</b><button type="button" data-shop-step="1" data-name="${esc(item.name)}">+</button></div></div></div>`).join('') : '<p>No items selected.</p>';
            total.textContent = money(selected.reduce((sum, item) => sum + item.price * item.qty, 0))
        }

        function adjust(card, step) {
            const input = card.querySelector('.shop-quantity'),
                max = +(input.max || 99);
            input.value = Math.max(0, Math.min(max, (+input.value || 0) + step));
            draw()
        }
        cards.forEach(card => card.querySelector('.shop-add').addEventListener('click', () => adjust(card, 1)));
        box.addEventListener('click', event => {
            const button = event.target.closest('[data-shop-step]');
            if (!button) return;
            const card = cards.find(item => item.dataset.name === button.dataset.name);
            if (card) adjust(card, +button.dataset.shopStep)
        });

        function filter() {
            const term = (search?.value || '').trim().toLowerCase();
            cards.forEach(card => {
                const okCat = category === 'all' || card.dataset.category === category,
                    okText = card.dataset.name.toLowerCase().includes(term);
                card.hidden = !(okCat && okText)
            })
        }
        buttons.forEach(button => button.addEventListener('click', () => {
            buttons.forEach(item => item.classList.remove('active'));
            button.classList.add('active');
            category = button.dataset.shopCategory;
            filter()
        }));
        search?.addEventListener('input', filter);
        draw();
        filter()
    })();
</script>
<script>
    (() => {
        const tabs = document.querySelector('.shop-category-tabs');
        document.querySelectorAll('[data-shop-scroll]').forEach(button => button.addEventListener('click', () => tabs?.scrollBy({
            left: Number(button.dataset.shopScroll) * 260,
            behavior: 'smooth'
        })));
        document.querySelectorAll('[data-shop-category]').forEach(button => button.addEventListener('click', () => button.scrollIntoView({
            behavior: 'smooth',
            inline: 'center',
            block: 'nearest'
        })))
    })();
</script>
@endsection