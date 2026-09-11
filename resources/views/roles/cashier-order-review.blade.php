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
                <span>{{ $order->customer?->name }}</span>
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
                        <div class="review-actions">
                            <button class="save-review" type="submit">Save order changes</button>
                            <button class="add-more-items" type="button" data-open-add-items @disabled($availableProducts->isEmpty()) @if($availableProducts->isEmpty()) title="Every available menu item is already in this order" @endif>
                                <span aria-hidden="true">+</span>
                                Add more items
                            </button>
                        </div>

                        @if($availableProducts->isNotEmpty())
                            <dialog class="add-items-dialog" id="add-items-dialog" aria-labelledby="add-items-title" @if(old('intent') === 'add_items' && $errors->any()) data-reopen @endif>
                                <div class="add-items-panel">
                                    <header class="add-items-head">
                                        <div><p>ADD TO ORDER</p><h2 id="add-items-title">Choose more menu items</h2><span>New items use today&rsquo;s menu price.</span></div>
                                        <button type="button" class="dialog-close" data-close-add-items aria-label="Close add items">&times;</button>
                                    </header>
                                    <label class="add-items-search">
                                        <span aria-hidden="true">&#128269;</span>
                                        <input id="add-items-search" type="search" placeholder="Search food or category" autocomplete="off">
                                    </label>
                                    <div class="add-items-catalog" id="add-items-catalog">
                                        @foreach($availableProducts as $product)
                                            @php($productImageUrl = $product->imageUrl())
                                            <article class="add-product" data-add-product data-name="{{ str($product->name.' '.$product->category)->lower() }}" data-price="{{ $product->price }}">
                                                @if($productImageUrl)
                                                    <img src="{{ $productImageUrl }}" alt="">
                                                @else
                                                    <span class="add-product-placeholder" aria-hidden="true">{{ str($product->name)->substr(0, 1)->upper() }}</span>
                                                @endif
                                                <div class="add-product-copy">
                                                    <small>{{ $product->category }}</small>
                                                    <strong>{{ $product->name }}</strong>
                                                    <span>&#8369;{{ number_format($product->price, 2) }} &middot; {{ $product->stock }} available</span>
                                                </div>
                                                <div class="add-product-quantity">
                                                    <button type="button" data-add-step="-1" aria-label="Remove one {{ $product->name }}">&minus;</button>
                                                    <input class="new-item-quantity" name="new_quantities[{{ $product->id }}]" type="number" min="0" max="{{ min($product->stock, 22) }}" value="{{ old('new_quantities.'.$product->id, 0) }}" aria-label="Quantity for {{ $product->name }}">
                                                    <button type="button" data-add-step="1" aria-label="Add one {{ $product->name }}">+</button>
                                                </div>
                                            </article>
                                        @endforeach
                                        <p class="add-items-empty" hidden>No menu items match your search.</p>
                                    </div>
                                    <footer class="add-items-footer">
                                        <span id="add-items-count" aria-live="polite">No new items selected</span>
                                        <div>
                                            <button class="add-items-cancel" type="button" data-close-add-items>Cancel</button>
                                            <button class="add-items-submit" type="submit" name="intent" value="add_items">Add selected items</button>
                                        </div>
                                    </footer>
                                </div>
                            </dialog>
                        @endif
                    </form>
                </section>

                <aside class="review-card payment-review">
                    <div class="card-title"><div><p>PAYMENT CHECK</p><h2>Confirm payment</h2></div></div>
                    @if($order->reservation)
                        <div class="reservation-charge"><span>{{ $order->reservation->table_size }} reserved seats &middot; {{ $order->reservation->reservation_at->format('M d') }} &middot; {{ $order->reservation->time_range }} &middot; {{ ucfirst($order->reservation->booking_status) }}</span><strong>Reservation fee: &#8369;{{ number_format($order->reservation->total_amount, 2) }}</strong></div>
                    @endif
                    @if($order->payment_method === 'gcash')
                        <div class="payment-state paid"><span>✓</span><div><b>Already submitted through GCash</b><small>Verify this reference before confirming.</small></div></div>
                        <div class="reference-box"><span>GCash reference</span><strong>{{ $order->payment_reference }}</strong></div>
                    @else
                        <div class="payment-state unpaid"><span>!</span><div><b>Not yet paid</b><small>The customer must pay the reviewed total in cash.</small></div></div>
                    @endif

                    <div class="confirm-total"><span>Amount due</span><strong>&#8369;{{ number_format($order->totalDue(), 2) }}</strong></div>
                    <form id="confirm-payment-form" method="POST" action="{{ route('cashier.orders.confirm-payment', $order) }}" onsubmit="return confirm('Confirm that the payment for this complete order has been received?')">
                        @csrf @method('PATCH')
                        @if($order->payment_method === 'cash')
                            <label for="cash_received">Customer cash</label>
                            <div class="cash-entry"><span>&#8369;</span><input id="cash_received" name="cash_received" type="number" min="{{ $order->totalDue() }}" max="99999999.99" step="0.01" value="{{ old('cash_received') }}" required placeholder="0.00"></div>
                            <div class="change-preview"><span>Change</span><strong id="cash-change">&#8369;0.00</strong></div>
                        @endif
                    </form>
                    <form id="reject-order-form" method="POST" action="{{ route('cashier.orders.reject', $order) }}">
                        @csrf @method('PATCH')
                    </form>
                    <div class="payment-actions">
                        <button class="reject-order" type="button" data-open-reject-order>Reject order</button>
                        <button class="confirm-payment" type="submit" form="confirm-payment-form">{{ $order->payment_method === 'gcash' ? 'Verify GCash and confirm paid' : 'Receive cash and confirm paid' }} <span>→</span></button>
                    </div>
                    <p class="confirm-note">Confirming sends this sale to the Admin and Super Admin dashboards and reports.</p>

                    <dialog class="reject-dialog" id="reject-order-dialog" aria-labelledby="reject-order-title">
                        <div class="reject-dialog-panel">
                            <span class="reject-dialog-icon" aria-hidden="true">!</span>
                            <p>ORDER DECISION</p>
                            <h2 id="reject-order-title">Reject order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}?</h2>
                            <span class="reject-dialog-copy">This removes the order from the payment queue, returns its reserved items to stock, and closes the linked reservation. No sale will be recorded.</span>
                            <div class="reject-dialog-actions">
                                <button type="button" data-close-reject-order>Keep order</button>
                                <button class="reject-dialog-confirm" type="submit" form="reject-order-form">Reject order</button>
                            </div>
                        </div>
                    </dialog>
                </aside>
            </div>
        </div>
    </main>
</div>
@push('styles')
<style>
.review-workspace{background:#f3f4ed!important}.review-page{width:min(1120px,100%);margin:auto}.review-head{margin-bottom:22px}.review-head>a{display:inline-flex;margin-bottom:20px;color:#606800;font-size:12px;font-weight:800}.review-head p,.card-title p{margin:0 0 6px;color:#7d8600;font-size:10px;font-weight:850;letter-spacing:.14em}.review-head h1{font-size:32px;margin:0;letter-spacing:-.04em}.review-head>span{display:block;color:#747a71;margin-top:6px}.review-error{padding:12px 14px;margin-bottom:18px;border-radius:10px;background:#fff0f0;color:#b42318}.review-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;align-items:start}.review-card{background:#fff;border:1px solid #daddd2;border-radius:18px;padding:22px;box-shadow:0 12px 35px rgba(25,27,23,.05)}.card-title{display:flex;justify-content:space-between;align-items:flex-start;gap:15px;padding-bottom:15px;border-bottom:1px solid #e5e7df}.card-title h2{margin:0;font-size:18px}.card-title>span{font-size:11px;color:#777d74}.editable-items{display:grid}.editable-items label{display:grid;grid-template-columns:minmax(0,1fr) 82px;align-items:center;gap:16px;padding:14px 0;border-bottom:1px solid #eceee7;margin:0}.editable-items label>span{display:grid}.editable-items small{color:#777d74;margin-top:4px}.editable-items input{width:82px;text-align:center}.edit-total,.confirm-total,.change-preview{display:flex;justify-content:space-between;align-items:center}.edit-total{padding:18px 0}.edit-total strong,.confirm-total strong{font-size:23px}.save-review,.confirm-payment{width:100%;min-height:46px;border:0;border-radius:11px;padding:0 16px;background:#171817;color:#fff;font-weight:800;cursor:pointer}.payment-review{position:sticky;top:0}.reservation-charge{display:grid;gap:4px;margin:16px 0;padding:12px;border-radius:10px;background:#f1f2e8;color:#686e65;font-size:11px}.reservation-charge strong{color:#282a27;font-size:13px}.payment-state{display:flex;gap:11px;align-items:center;border-radius:12px;padding:14px;margin:16px 0}.payment-state>span{width:34px;height:34px;flex:0 0 auto;border-radius:50%;display:grid;place-items:center;font-weight:900}.payment-state>div{display:grid}.payment-state small{margin-top:3px}.payment-state.paid{background:#e8f5ed;color:#236b49}.payment-state.paid>span{background:#cae8d6}.payment-state.unpaid{background:#fff1e8;color:#9b4b16}.payment-state.unpaid>span{background:#f5d5bf}.reference-box{display:grid;gap:5px;padding:13px;border:1px dashed #9fb4d2;border-radius:11px;background:#f3f7ff}.reference-box span{font-size:11px;color:#66758a}.reference-box strong{font-size:19px;letter-spacing:.08em}.confirm-total{padding:19px 0;border-bottom:1px solid #e4e6df;margin-bottom:16px}.payment-review form>label{font-size:12px;font-weight:800}.cash-entry{display:flex;align-items:center;border:1px solid #cfd3c7;border-radius:10px;margin:7px 0 10px}.cash-entry span{padding-left:11px}.cash-entry input{width:100%;border:0!important;box-shadow:none!important;font-size:18px}.change-preview{padding:12px;background:#eff1df;border-radius:10px;margin-bottom:14px}.confirm-payment{display:flex;align-items:center;justify-content:space-between}.confirm-note{font-size:11px;line-height:1.45;color:#777d74;margin:12px 0 0}
.review-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}.add-more-items{min-height:46px;border:1px solid #c9cec1;border-radius:11px;padding:0 15px;background:#fff;color:#20221f;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}.add-more-items>span{width:21px;height:21px;border-radius:50%;background:#eff1df;color:#606900;display:grid;place-items:center;font-size:18px;line-height:1}.add-more-items:hover{border-color:#879000;background:#f8f9ef;transform:translateY(-1px)}.add-more-items:disabled{cursor:not-allowed;opacity:.5;transform:none}.payment-actions{display:grid;grid-template-columns:112px minmax(0,1fr);gap:9px}.reject-order{min-height:46px;border:1px solid #b42318;border-radius:11px;padding:0 13px;background:#fff7f7;color:#b42318;font-weight:800;cursor:pointer}.reject-order:hover{background:#b42318;color:#fff;transform:translateY(-1px)}
.reject-dialog{width:min(430px,calc(100% - 28px));max-width:none;margin:auto;padding:0;border:0;border-radius:18px;background:transparent;color:#171817;overflow:hidden;box-shadow:0 26px 80px rgba(45,12,9,.3)}.reject-dialog::backdrop{background:rgba(17,19,16,.58);backdrop-filter:blur(3px)}.reject-dialog[open]{animation:reject-order-in .22s cubic-bezier(.2,.8,.2,1)}.reject-dialog[open]::backdrop{animation:add-items-shade .2s ease-out}.reject-dialog-panel{padding:28px;background:#fff;text-align:center}.reject-dialog-icon{width:52px;height:52px;margin:0 auto 16px;border-radius:50%;background:#fde7e5;color:#b42318;display:grid;place-items:center;font-size:25px;font-weight:900}.reject-dialog-panel>p{margin:0 0 7px;color:#b42318;font-size:10px;font-weight:850;letter-spacing:.14em}.reject-dialog-panel h2{margin:0;font-size:23px}.reject-dialog-copy{display:block;margin:11px 0 22px;color:#686f65;font-size:13px;line-height:1.55}.reject-dialog-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px}.reject-dialog-actions button{min-height:44px;border:1px solid #ccd1c5;border-radius:10px;background:#fff;color:#2d302c;font-weight:800;cursor:pointer}.reject-dialog-actions button:hover{background:#f2f3ed;transform:translateY(-1px)}.reject-dialog-actions .reject-dialog-confirm{border-color:#b42318;background:#b42318;color:#fff}.reject-dialog-actions .reject-dialog-confirm:hover{background:#941d14}@keyframes reject-order-in{from{opacity:0;transform:translateY(14px) scale(.96)}to{opacity:1;transform:none}}
.add-items-dialog{width:min(820px,calc(100% - 28px));max-width:none;max-height:min(760px,calc(100dvh - 28px));margin:auto;padding:0;border:0;border-radius:18px;background:transparent;color:#171817;overflow:hidden;box-shadow:0 28px 90px rgba(13,15,12,.28)}.add-items-dialog::backdrop{background:rgba(17,19,16,.58);backdrop-filter:blur(3px)}.add-items-dialog[open]{animation:add-items-rise .24s cubic-bezier(.2,.8,.2,1)}.add-items-dialog[open]::backdrop{animation:add-items-shade .2s ease-out}.add-items-panel{max-height:min(760px,calc(100dvh - 28px));display:grid;grid-template-rows:auto auto minmax(180px,1fr) auto;background:#f7f8f2}.add-items-head{display:flex;justify-content:space-between;gap:20px;padding:22px 24px 17px;background:#fff;border-bottom:1px solid #e1e4da}.add-items-head p{margin:0 0 5px;color:#7d8600;font-size:10px;font-weight:850;letter-spacing:.14em}.add-items-head h2{margin:0;font-size:23px}.add-items-head span{display:block;margin-top:5px;color:#70766d;font-size:12px}.dialog-close{width:38px;height:38px;flex:0 0 auto;border:1px solid #d5d8cf;border-radius:50%;background:#fff;color:#4e544c;font-size:25px;line-height:1;cursor:pointer}.dialog-close:hover{background:#171817;color:#fff;transform:rotate(5deg)}.add-items-search{margin:16px 20px 5px;min-height:44px;display:flex;align-items:center;gap:9px;border:1px solid #d4d8cc;border-radius:10px;background:#fff;padding:0 13px}.add-items-search:focus-within{border-color:#858f00;box-shadow:0 0 0 3px rgba(171,184,13,.14)}.add-items-search input{width:100%;height:42px;border:0!important;outline:0!important;box-shadow:none!important;background:transparent}.add-items-catalog{padding:12px 20px 18px;display:grid;grid-template-columns:1fr 1fr;gap:10px;overflow:auto}.add-product{min-width:0;display:grid;grid-template-columns:58px minmax(0,1fr) auto;gap:12px;align-items:center;padding:11px;border:1px solid #dde0d7;border-radius:13px;background:#fff;transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}.add-product.is-selected{border-color:#aab514;background:#fbfced;box-shadow:inset 3px 0 #aab514}.add-product:hover{border-color:#b8bda9;box-shadow:0 8px 22px rgba(25,27,23,.06);transform:translateY(-1px)}.add-product[hidden]{display:none!important}.add-product>img,.add-product-placeholder{width:58px;height:58px;border-radius:10px;object-fit:cover;background:#eef0e4}.add-product-placeholder{display:grid;place-items:center;color:#6b7400;font-size:22px;font-weight:900}.add-product-copy{min-width:0;display:grid;gap:3px}.add-product-copy small{color:#7b8308;font-size:9px;font-weight:850;letter-spacing:.08em;text-transform:uppercase}.add-product-copy strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.add-product-copy span{color:#737970;font-size:11px}.add-product-quantity{display:grid;grid-template-columns:28px 42px 28px;align-items:center}.add-product-quantity button{width:28px;height:30px;border:1px solid #d3d7cd;background:#f8f9f5;color:#343732;font-size:17px;cursor:pointer}.add-product-quantity button:first-child{border-radius:8px 0 0 8px}.add-product-quantity button:last-child{border-radius:0 8px 8px 0}.add-product-quantity button:hover{background:#e9ecd2;color:#5e6700}.add-product-quantity input{width:42px;height:30px;border-block:1px solid #d3d7cd!important;border-inline:0!important;border-radius:0!important;padding:0!important;text-align:center;box-shadow:none!important;-moz-appearance:textfield}.add-product-quantity input::-webkit-inner-spin-button{appearance:none}.add-items-empty{grid-column:1/-1;margin:35px 0;text-align:center;color:#777d74}.add-items-footer{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:15px 20px;border-top:1px solid #dfe2d8;background:#fff}.add-items-footer>span{color:#656c62;font-size:12px;font-weight:700}.add-items-footer>div{display:flex;gap:8px}.add-items-cancel,.add-items-submit{min-height:42px;border-radius:9px;padding:0 15px;font-weight:800;cursor:pointer}.add-items-cancel{border:1px solid #cfd3c8;background:#fff}.add-items-submit{border:1px solid #171817;background:#171817;color:#fff}.add-items-submit:disabled{opacity:.45;cursor:not-allowed}.add-items-submit:hover:not(:disabled){background:#30322e;transform:translateY(-1px)}@keyframes add-items-rise{from{opacity:0;transform:translateY(22px) scale(.985)}to{opacity:1;transform:none}}@keyframes add-items-shade{from{opacity:0}to{opacity:1}}
@media(max-width:900px){.review-grid{grid-template-columns:1fr}.payment-review{position:static;order:-1}}@media(max-width:700px){.add-items-catalog{grid-template-columns:1fr}}@media(max-width:560px){.review-card{padding:17px}.card-title{flex-direction:column}.editable-items label{grid-template-columns:minmax(0,1fr) 70px}.editable-items input{width:70px}.review-head h1{font-size:27px}.review-actions,.payment-actions{grid-template-columns:1fr}.payment-actions .reject-order{order:2}.add-items-dialog{width:calc(100% - 16px);max-height:calc(100dvh - 16px);border-radius:14px}.add-items-panel{max-height:calc(100dvh - 16px)}.add-items-head{padding:18px 16px 14px}.add-items-head h2{font-size:20px}.add-items-search{margin:12px 12px 4px}.add-items-catalog{padding:9px 12px 14px}.add-product{grid-template-columns:48px minmax(0,1fr);gap:10px}.add-product>img,.add-product-placeholder{width:48px;height:48px}.add-product-quantity{grid-column:1/-1;grid-template-columns:36px 1fr 36px}.add-product-quantity button{width:36px}.add-product-quantity input{width:100%}.add-items-footer{align-items:stretch;padding:12px;flex-direction:column}.add-items-footer>div{display:grid;grid-template-columns:1fr 1fr}}@media(prefers-reduced-motion:reduce){.add-items-dialog[open],.add-items-dialog[open]::backdrop{animation:none}.add-product,.add-more-items,.reject-order{transition:none!important}}
@media(prefers-reduced-motion:reduce){.reject-dialog[open],.reject-dialog[open]::backdrop{animation:none}}
</style>
@endpush
<script>
(() => {
    const quantities=[...document.querySelectorAll('.review-quantity')],newQuantities=[...document.querySelectorAll('.new-item-quantity')],total=document.getElementById('review-total');
    const money=value=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(value);
    const reviewedTotal=()=>quantities.reduce((sum,input)=>sum+(+input.value||0)*(+input.closest('label').dataset.price),0)+newQuantities.reduce((sum,input)=>sum+(+input.value||0)*(+input.closest('[data-add-product]').dataset.price),0);
    const update=()=>{total.textContent=money(reviewedTotal())};
    [...quantities,...newQuantities].forEach(input=>input.addEventListener('input',update)); update();

    const dialog=document.getElementById('add-items-dialog'),openButton=document.querySelector('[data-open-add-items]'),search=document.getElementById('add-items-search'),rows=[...document.querySelectorAll('[data-add-product]')],addButton=document.querySelector('.add-items-submit'),selectedCount=document.getElementById('add-items-count'),empty=document.querySelector('.add-items-empty');
    const updateSelection=()=>{
        const count=newQuantities.reduce((sum,input)=>sum+(+input.value||0),0),addedTotal=newQuantities.reduce((sum,input)=>sum+(+input.value||0)*(+input.closest('[data-add-product]').dataset.price),0);
        rows.forEach(row=>row.classList.toggle('is-selected',+(row.querySelector('.new-item-quantity')?.value||0)>0));
        if(addButton)addButton.disabled=count<1;
        if(selectedCount)selectedCount.textContent=count?`${count} new ${count===1?'item':'items'} · ${money(addedTotal)}`:'No new items selected';
        update();
    };
    rows.forEach(row=>row.querySelectorAll('[data-add-step]').forEach(button=>button.addEventListener('click',()=>{const input=row.querySelector('.new-item-quantity'),next=Math.max(+input.min,Math.min(+input.max,(+input.value||0)+(+button.dataset.addStep)));input.value=next;input.dispatchEvent(new Event('input',{bubbles:true}))})));
    newQuantities.forEach(input=>input.addEventListener('input',()=>{input.value=Math.max(+input.min,Math.min(+input.max,+input.value||0));updateSelection()}));
    const openDialog=()=>{if(!dialog)return;if(typeof dialog.showModal==='function'){if(!dialog.open)dialog.showModal()}else{dialog.setAttribute('open','')}requestAnimationFrame(()=>search?.focus())};
    const closeDialog=()=>{if(!dialog)return;newQuantities.forEach(input=>{input.value=0});updateSelection();if(typeof dialog.close==='function')dialog.close();else dialog.removeAttribute('open');openButton?.focus()};
    openButton?.addEventListener('click',openDialog);
    document.querySelectorAll('[data-close-add-items]').forEach(button=>button.addEventListener('click',closeDialog));
    dialog?.addEventListener('click',event=>{if(event.target===dialog)closeDialog()});
    dialog?.addEventListener('cancel',event=>{event.preventDefault();closeDialog()});
    search?.addEventListener('input',()=>{const term=search.value.trim().toLocaleLowerCase();let visible=0;rows.forEach(row=>{const show=!term||row.dataset.name.includes(term);row.hidden=!show;if(show)visible++});if(empty)empty.hidden=visible>0});
    updateSelection();
    if(dialog?.hasAttribute('data-reopen'))openDialog();

    const rejectDialog=document.getElementById('reject-order-dialog'),rejectOpen=document.querySelector('[data-open-reject-order]');
    const openReject=()=>{if(!rejectDialog)return;if(typeof rejectDialog.showModal==='function'){if(!rejectDialog.open)rejectDialog.showModal()}else{rejectDialog.setAttribute('open','')}requestAnimationFrame(()=>rejectDialog.querySelector('[data-close-reject-order]')?.focus())};
    const closeReject=()=>{if(!rejectDialog)return;if(typeof rejectDialog.close==='function')rejectDialog.close();else rejectDialog.removeAttribute('open');rejectOpen?.focus()};
    rejectOpen?.addEventListener('click',openReject);
    document.querySelectorAll('[data-close-reject-order]').forEach(button=>button.addEventListener('click',closeReject));
    rejectDialog?.addEventListener('click',event=>{if(event.target===rejectDialog)closeReject()});
    rejectDialog?.addEventListener('cancel',event=>{event.preventDefault();closeReject()});

    const cash=document.getElementById('cash_received'),change=document.getElementById('cash-change');
    if(cash){const due={{ $order->totalDue() }};const updateCash=()=>change.textContent=money(Math.max(0,(+cash.value||0)-due));cash.addEventListener('input',updateCash);updateCash()}
})();
</script>
@endsection
