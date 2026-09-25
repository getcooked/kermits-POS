@extends('layouts.app')
@section('title', $isSuperAdmin ? 'Cashier Sales History' : 'My Sales History')
@section('content')
<div class="admin-shell sales-history-shell">
    @include('partials.admin-sidebar')
    <main class="admin-workspace sales-history-workspace">
        <div class="sales-history">
            <header class="history-head">
                <div>
                    <p>{{ $isSuperAdmin ? 'STAFF PERFORMANCE' : 'MY PERFORMANCE' }}</p>
                    <h1>{{ $isSuperAdmin ? 'Cashier sales history' : 'My sales history' }}</h1>
                    <span>{{ $periodFrom->format('M d, Y') }}{{ $periodFrom->isSameDay($periodTo) ? '' : ' - '.$periodTo->format('M d, Y') }}</span>
                </div>
                <div class="history-periods" aria-label="Sales period">
                    @foreach(['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $key => $label)
                        <a class="{{ $period === $key ? 'active' : '' }}" href="{{ route('sales-history.index', array_filter(['period' => $key, 'payment_method' => request('payment_method'), 'cashier_id' => $isSuperAdmin ? request('cashier_id') : null])) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </header>

            <form class="history-filters" method="GET" action="{{ route('sales-history.index') }}">
                <div>
                    <label for="history-period">Period</label>
                    <select id="history-period" name="period">
                        <option value="today" @selected($period === 'today')>Today</option>
                        <option value="week" @selected($period === 'week')>This week</option>
                        <option value="month" @selected($period === 'month')>This month</option>
                        <option value="custom" @selected($period === 'custom')>Custom dates</option>
                    </select>
                </div>
                <div>
                    <label for="history-from">From</label>
                    <input id="history-from" type="date" name="from" value="{{ request('from', $periodFrom->format('Y-m-d')) }}">
                </div>
                <div>
                    <label for="history-to">To</label>
                    <input id="history-to" type="date" name="to" value="{{ request('to', $periodTo->format('Y-m-d')) }}">
                </div>
                <div>
                    <label for="history-payment">Payment</label>
                    <select id="history-payment" name="payment_method">
                        <option value="">All payments</option>
                        <option value="cash" @selected(request('payment_method') === 'cash')>Cash</option>
                        <option value="gcash" @selected(request('payment_method') === 'gcash')>GCash</option>
                        <option value="paymongo" @selected(request('payment_method') === 'paymongo')>PayMongo</option>
                    </select>
                </div>
                @if($isSuperAdmin)
                    <div>
                        <label for="history-cashier">Cashier</label>
                        <select id="history-cashier" name="cashier_id">
                            <option value="">All cashiers and online</option>
                            @foreach($cashiers as $cashier)
                                <option value="{{ $cashier->id }}" @selected((string) request('cashier_id') === (string) $cashier->id)>{{ $cashier->name }}{{ $cashier->trashed() ? ' (Deleted)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="history-search">
                    <label for="history-search">Receipt, customer, or reference</label>
                    <input id="history-search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="#000124 or customer name">
                </div>
                <button type="submit">Apply filters</button>
                <a class="history-reset" href="{{ route('sales-history.index') }}">Reset</a>
            </form>

            @if($errors->any())
                <div class="error history-error">{{ $errors->first() }}</div>
            @endif

            <section class="history-metrics">
                <article><span>Total sales</span><strong>&#8369;{{ number_format($salesTotal, 2) }}</strong><small>Paid transactions only</small></article>
                <article><span>Transactions</span><strong>{{ number_format($transactionCount) }}</strong><small>Completed payments</small></article>
                <article><span>Average sale</span><strong>&#8369;{{ number_format($averageSale, 2) }}</strong><small>Per transaction</small></article>
                <article><span>Selected period</span><strong>{{ ucfirst($period) }}</strong><small>{{ $periodFrom->format('M d') }}{{ $periodFrom->isSameDay($periodTo) ? '' : ' - '.$periodTo->format('M d') }}</small></article>
            </section>

            <section class="payment-summary-bar">
                <div class="cash"><span>Cash</span><strong>&#8369;{{ number_format($cashTotal, 2) }}</strong></div>
                <div class="gcash"><span>GCash</span><strong>&#8369;{{ number_format($gcashTotal, 2) }}</strong></div>
                <div class="paymongo"><span>PayMongo</span><strong>&#8369;{{ number_format($paymongoTotal, 2) }}</strong></div>
            </section>

            <section class="history-content {{ $isSuperAdmin ? '' : 'cashier-only' }}">
                <article class="history-card transactions-card">
                    <div class="history-card-head">
                        <div><p>TRANSACTIONS</p><h2>{{ $isSuperAdmin ? 'Paid sales' : 'Sales I processed' }}</h2></div>
                        <a href="{{ route('sales-history.export', request()->query()) }}">Export CSV</a>
                    </div>
                    <div class="history-table-wrap">
                        <table>
                            <thead><tr><th>Time</th><th>Receipt</th><th>Customer</th>@if($isSuperAdmin)<th>Processed by</th>@endif<th>Payment</th><th>Total</th><th></th></tr></thead>
                            <tbody>
                            @forelse($orders as $order)
                                <tr>
                                    <td>{{ $order->paid_at?->format('M d, Y') }}<small>{{ $order->paid_at?->format('h:i A') }}</small></td>
                                    <td><strong>#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</strong></td>
                                    <td>{{ $order->customer?->name ?? 'Walk-in Customer' }}<small>{{ $order->customer?->email ?? 'Counter sale' }}</small></td>
                                    @if($isSuperAdmin)<td>{{ $order->processor?->name ?? 'Online / System' }}</td>@endif
                                    <td><span class="history-payment {{ $order->payment_method }}">{{ match($order->payment_method) { 'gcash' => 'GCash', 'paymongo' => 'PayMongo', default => 'Cash' } }}</span><small>{{ $order->payment_reference ?: 'No reference' }}</small></td>
                                    <td><strong>&#8369;{{ number_format($order->totalDue(), 2) }}</strong></td>
                                    <td><a class="receipt-link" href="{{ route('receipts.show', [$order, 'return' => 'sales-history']) }}">Receipt</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $isSuperAdmin ? 7 : 6 }}" class="history-empty">No paid sales match the selected filters.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($orders->hasPages())
                        <nav class="history-pagination" aria-label="Sales history pages">
                            @if($orders->onFirstPage())<span>Previous</span>@else<a href="{{ $orders->previousPageUrl() }}">Previous</a>@endif
                            <strong>Page {{ $orders->currentPage() }} of {{ $orders->lastPage() }}</strong>
                            @if($orders->hasMorePages())<a href="{{ $orders->nextPageUrl() }}">Next</a>@else<span>Next</span>@endif
                        </nav>
                    @endif
                </article>

                @if($isSuperAdmin)
                    <article class="history-card comparison-card">
                        <div class="history-card-head"><div><p>PERFORMANCE</p><h2>Cashier comparison</h2></div></div>
                        <div class="comparison-list">
                            @forelse($cashierComparison as $position => $cashier)
                                <div>
                                    <span class="comparison-rank">{{ $position + 1 }}</span>
                                    <span><strong>{{ $cashier['name'] }}</strong><small>{{ $cashier['count'] }} {{ $cashier['count'] === 1 ? 'transaction' : 'transactions' }}</small></span>
                                    <b>&#8369;{{ number_format($cashier['sales'], 2) }}</b>
                                </div>
                            @empty
                                <p class="history-empty">No cashier sales in this period.</p>
                            @endforelse
                        </div>
                    </article>
                @endif
            </section>
        </div>
    </main>
</div>
@push('styles')
<style>
.sales-history-workspace{background:#f3f4ed!important}.sales-history{width:min(1460px,100%);margin:auto}.history-head{display:flex;align-items:center;justify-content:space-between;gap:24px;margin-bottom:20px}.history-head p,.history-card-head p{margin:0 0 5px;color:#7d8600;font-size:10px;font-weight:850;letter-spacing:.14em}.history-head h1{margin:0;font-size:31px;letter-spacing:-.04em}.history-head>div>span{display:block;margin-top:6px;color:#73796f;font-size:13px}.history-periods{display:flex;padding:4px;border-radius:12px;background:#e6e8df}.history-periods a{min-height:38px;padding:0 14px;border-radius:9px;color:#666c63;display:grid;place-items:center;text-decoration:none;font-size:12px;font-weight:750}.history-periods a.active{background:#171817;color:#fff;box-shadow:0 5px 13px #17181728}.history-filters{display:grid;grid-template-columns:repeat(4,minmax(115px,1fr)) minmax(190px,1.4fr) auto auto;gap:10px;align-items:end;margin-bottom:14px;padding:15px;border:1px solid #dedfd8;border-radius:15px;background:#fff}.history-filters>div{min-width:0}.history-filters label{display:block;margin:0 0 5px;color:#555b52;font-size:10px;font-weight:800}.history-filters input,.history-filters select{box-sizing:border-box;width:100%;height:41px;padding:0 10px;border:1px solid #d5d8cf;border-radius:9px;background:#fff;color:#222;font:inherit;font-size:12px}.history-filters button,.history-reset{height:41px;padding:0 15px;border:0;border-radius:9px;background:#171817;color:#fff;display:grid;place-items:center;text-decoration:none;font:800 12px inherit;cursor:pointer}.history-reset{background:#eff0eb;color:#62675f}.history-error{margin-bottom:14px;padding:12px 14px;border-radius:10px}.history-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:13px;margin-bottom:13px}.history-metrics article{position:relative;min-height:112px;padding:18px;border:1px solid #e0e2da;border-radius:16px;background:#fff;box-shadow:0 8px 25px rgba(24,27,22,.04);overflow:hidden}.history-metrics article:after{content:"";position:absolute;right:-22px;bottom:-42px;width:95px;height:95px;border-radius:50%;background:#f0f2df}.history-metrics span,.history-metrics small{position:relative;z-index:1;display:block;color:#747a71;font-size:11px}.history-metrics strong{position:relative;z-index:1;display:block;margin:7px 0 4px;font-size:25px;letter-spacing:-.03em}.payment-summary-bar{display:grid;grid-template-columns:repeat(3,1fr);margin-bottom:13px;border:1px solid #e0e2da;border-radius:14px;background:#fff;overflow:hidden}.payment-summary-bar>div{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:15px;border-left:1px solid #e7e9e2}.payment-summary-bar>div:first-child{border-left:0}.payment-summary-bar span{font-size:11px;font-weight:800}.payment-summary-bar strong{font-size:15px}.payment-summary-bar .cash span{color:#27725a}.payment-summary-bar .gcash span{color:#1966bd}.payment-summary-bar .paymongo span{color:#6849bd}.history-content{display:grid;grid-template-columns:minmax(0,2.4fr) minmax(260px,.8fr);gap:13px;align-items:start}.history-content.cashier-only{grid-template-columns:1fr}.history-card{min-width:0;padding:20px;border:1px solid #e0e2da;border-radius:17px;background:#fff;box-shadow:0 8px 25px rgba(24,27,22,.04)}.history-card-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}.history-card-head h2{margin:0;font-size:18px}.history-card-head>a{padding:9px 11px;border-radius:9px;background:#eff1df;color:#626b00;text-decoration:none;font-size:11px;font-weight:850}.history-table-wrap{overflow:auto;scrollbar-width:none}.history-table-wrap::-webkit-scrollbar{display:none}.history-table-wrap table{width:100%;min-width:760px;border-collapse:collapse}.history-table-wrap th{padding:10px;background:#f3f4ef;color:#777d74;text-align:left;font-size:9px;letter-spacing:.07em;text-transform:uppercase}.history-table-wrap td{padding:12px 10px;border-bottom:1px solid #eceee7;font-size:11px;vertical-align:middle}.history-table-wrap td small{display:block;max-width:190px;margin-top:3px;color:#858a82;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.history-payment{display:inline-flex;padding:5px 8px;border-radius:999px;background:#e8f4ef;color:#237159;font-size:9px;font-weight:850}.history-payment.gcash{background:#e7f0ff;color:#1762b9}.history-payment.paymongo{background:#eee9fc;color:#6044b9}.receipt-link{color:#626b00;font-weight:850}.history-empty{padding:35px!important;color:#777d74!important;text-align:center!important}.history-pagination{display:flex;align-items:center;justify-content:flex-end;gap:12px;margin-top:15px;font-size:11px}.history-pagination a,.history-pagination span{padding:7px 10px;border-radius:8px;background:#eff0eb;color:#63685f;text-decoration:none}.history-pagination span{opacity:.5}.comparison-list>div{display:grid;grid-template-columns:32px minmax(0,1fr) auto;align-items:center;gap:9px;padding:12px 0;border-top:1px solid #eceee7}.comparison-rank{width:28px;height:28px;border-radius:8px;background:#eff1df;color:#6b7400;display:grid;place-items:center;font-size:10px;font-weight:900}.comparison-list>div>span:nth-child(2){display:grid;gap:3px;min-width:0}.comparison-list strong{font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.comparison-list small{color:#7b8177;font-size:10px}.comparison-list b{font-size:11px;white-space:nowrap}
@media(max-width:1200px){.history-filters{grid-template-columns:repeat(3,minmax(130px,1fr))}.history-search{grid-column:span 2}.history-content{grid-template-columns:1fr}.comparison-card{order:-1}}
@media(max-width:800px){.history-head{align-items:flex-start;flex-direction:column}.history-periods{width:100%}.history-periods a{flex:1}.history-metrics{grid-template-columns:1fr 1fr}.history-filters{grid-template-columns:1fr 1fr}.history-search{grid-column:span 2}.payment-summary-bar{grid-template-columns:1fr}.payment-summary-bar>div{border-left:0;border-top:1px solid #e7e9e2}.payment-summary-bar>div:first-child{border-top:0}}
@media(max-width:520px){.history-metrics,.history-filters{grid-template-columns:1fr}.history-search{grid-column:auto}.history-card{padding:15px}.history-head h1{font-size:27px}.history-periods a{padding:0 8px;font-size:10px}}
</style>
@endpush
<script>
(() => {
    const period = document.getElementById('history-period');
    const from = document.getElementById('history-from');
    const to = document.getElementById('history-to');
    if (!period || !from || !to) return;
    const updateDates = () => {
        const custom = period.value === 'custom';
        from.required = custom;
        to.required = custom;
        from.disabled = !custom;
        to.disabled = !custom;
    };
    period.addEventListener('change', updateDates);
    updateDates();
})();
</script>
@endsection
