@extends('layouts.app')
@section('title','Reports')
@section('content')
<div class="admin-shell report-shell">
@include('partials.admin-sidebar')
<main class="admin-workspace report-workspace">
<div class="report-dashboard">
    <header class="report-topbar">
        <div>
            <p>BUSINESS OVERVIEW</p>
            <h1>Sales & operations</h1>
            <span>{{ $periodFrom->format('M d, Y') }} – {{ $periodTo->format('M d, Y') }}</span>
        </div>
        <div class="period-tabs" aria-label="Report period">
            @foreach(['week'=>'Weekly','month'=>'Monthly','year'=>'Yearly'] as $key=>$label)
                <a class="{{ $period===$key?'active':'' }}" href="{{ route('reports',['period'=>$key,'payment_method'=>request('payment_method')]) }}">{{ $label }}</a>
            @endforeach
            <button type="button" class="{{ $period==='custom'?'active':'' }}" data-filter-toggle>Custom</button>
        </div>
    </header>

    <form class="report-filter {{ $period==='custom'?'open':'' }}" method="GET" data-filter-form>
        <input type="hidden" name="period" value="custom">
        <div><label for="from">From</label><input class="control" id="from" type="date" name="from" value="{{ request('from',$periodFrom->format('Y-m-d')) }}" required></div>
        <div><label for="to">To</label><input class="control" id="to" type="date" name="to" value="{{ request('to',$periodTo->format('Y-m-d')) }}" required></div>
        <div><label for="payment_method">Payment</label><select class="control" id="payment_method" name="payment_method"><option value="">All payments</option><option value="cash" @selected(request('payment_method')==='cash')>Cash</option><option value="gcash" @selected(request('payment_method')==='gcash')>GCash</option></select></div>
        <button class="report-primary">Apply dates</button>
        <a href="{{ route('reports',['period'=>'month']) }}">Reset</a>
    </form>
    <section class="metric-grid">
        <article class="metric-card sales"><div class="metric-icon">&#8369;</div><span>Total sales</span><strong>&#8369;{{ number_format($salesTotal,2) }}</strong><small class="{{ $salesChange<0?'down':'up' }}">{{ $salesChange>=0?'↗':'↘' }} {{ number_format(abs($salesChange),1) }}% from prior period</small></article>
        <article class="metric-card orders"><div class="metric-icon">#</div><span>Filtered orders</span><strong>{{ $orders->count() }}</strong><small>Completed and paid sales</small></article>
        <article class="metric-card cash"><div class="metric-icon">&#8369;</div><span>Cash · {{ $cashCount }} sales</span><strong>&#8369;{{ number_format($cashTotal,2) }}</strong><small>{{ $salesTotal>0?number_format(((float)$cashTotal/$salesTotal)*100,1):0 }}% of filtered sales</small></article>
        <article class="metric-card gcash"><div class="metric-icon">G</div><span>GCash · {{ $gcashCount }} sales</span><strong>&#8369;{{ number_format($gcashTotal,2) }}</strong><small>{{ $salesTotal>0?number_format(((float)$gcashTotal/$salesTotal)*100,1):0 }}% of filtered sales</small></article>
    </section>

    <section class="dashboard-grid">
        <article class="dashboard-card sales-chart-card">
            <div class="card-heading"><div><p>SALES PERFORMANCE</p><h2>Cash and GCash sales</h2></div><span>{{ ucfirst($period) }} view</span></div>
            <div class="chart-wrap"><canvas id="sales-chart" aria-label="Interactive sales chart"></canvas><div class="chart-tooltip" hidden></div><div class="chart-empty" hidden>No paid sales in this period.</div></div>
            <div class="chart-legend"><span><i class="cash-dot"></i>Cash</span><span><i class="gcash-dot"></i>GCash</span></div>
        </article>
        <article class="dashboard-card payment-card-report">
            <div class="card-heading"><div><p>PAYMENTS</p><h2>Payment mix</h2></div></div>
            @php($paymentTotal=(float)$cashTotal+(float)$gcashTotal)
            @php($cashPercent=$paymentTotal>0?((float)$cashTotal/$paymentTotal)*100:0)
            <div class="donut" style="--cash:{{ $cashPercent }}"><div><strong>{{ $orders->count() }}</strong><span>payments</span></div></div>
            <div class="payment-breakdown"><div><span><i class="cash-dot"></i>Cash</span><strong>&#8369;{{ number_format($cashTotal,2) }}</strong></div><div><span><i class="gcash-dot"></i>GCash</span><strong>&#8369;{{ number_format($gcashTotal,2) }}</strong></div></div>
        </article>

        <article class="dashboard-card recent-sales">
            <div class="card-heading"><div><p>TRANSACTIONS</p><h2>Recent sales and receipts</h2></div><span>{{ $orders->count() }} records</span></div>
            <div class="table-scroll"><table><thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Payment</th><th>Total</th><th></th></tr></thead><tbody>
            @forelse($orders->take(10) as $order)<tr><td>#{{ $order->id }}</td><td><strong>{{ $order->customer?->name ?? 'Walk-in Customer' }}</strong><small>{{ $order->customer?->email ?? 'Cashier transaction' }}</small></td><td>{{ $order->created_at->format('M d, Y') }}<small>{{ $order->created_at->format('h:i A') }}</small></td><td><span class="payment-badge {{ $order->payment_method }}">{{ strtoupper($order->payment_method) }}</span><small>{{ $order->payment_reference ?: '—' }}</small></td><td><strong>&#8369;{{ number_format($order->total,2) }}</strong></td><td><a class="table-action" href="{{ route('receipts.show',$order) }}">Receipt</a></td></tr>
            @empty<tr><td colspan="6" class="empty-row">No paid transactions in this period.</td></tr>@endforelse
            </tbody></table></div>
        </article>
        <article class="dashboard-card stock-summary">
            <div class="card-heading"><div><p>STOCK HISTORY</p><h2>Inventory activity</h2></div><a href="{{ route('inventory.index') }}">Open inventory</a></div>
            <div class="stacked-metrics">
                <div><span>Stock received</span><strong>{{ number_format($stockIn) }}</strong><small>units added</small></div>
                <div><span>Stock released</span><strong>{{ number_format($stockOut) }}</strong><small>sold or removed</small></div>
                <div><span>Inventory value</span><strong>&#8369;{{ number_format($inventoryValue,2) }}</strong><small>current live value</small></div>
                <div class="{{ $lowStock?'attention':'' }}"><span>Low-stock alerts</span><strong>{{ $lowStock }}</strong><small>products need attention</small></div>
            </div>
        </article>

        <article class="dashboard-card best-products">
            <div class="card-heading"><div><p>PRODUCT PERFORMANCE</p><h2>Best-selling products</h2></div></div>
            <div class="rank-list">@forelse($topProducts as $item)<div><span class="rank-number">{{ $loop->iteration }}</span><span><strong>{{ $item->product?->name ?? 'Product' }}</strong><small>{{ $item->units }} units sold</small></span><b>&#8369;{{ number_format($item->sales,2) }}</b></div>@empty<p class="empty-row">No paid product sales yet.</p>@endforelse</div>
        </article>
        <article class="dashboard-card stock-alerts">
            <div class="card-heading"><div><p>LIVE INVENTORY</p><h2>Stock alert</h2></div></div>
            <div class="alert-list">@forelse($products->take(8) as $product)<div><span>{{ $product->name }}</span><strong class="{{ $product->stock<=5?'danger':'healthy' }}">{{ $product->stock }}</strong></div>@empty<p class="empty-row">No products available.</p>@endforelse</div>
        </article>

        <article class="dashboard-card reservation-card">
            <div class="card-heading"><div><p>BOOKING FLOW</p><h2>Reservation report</h2></div><a href="{{ route('reservations.index') }}">Manage reservations</a></div>
            <div class="reservation-metrics"><div><span>Total requests</span><strong>{{ $reservationCount }}</strong></div><div><span>Waiting approval</span><strong>{{ $pendingReservations }}</strong></div><div><span>Approved/completed</span><strong>{{ $approvedReservations }}</strong></div><div><span>Approved value</span><strong>&#8369;{{ number_format($approvedReservationValue,2) }}</strong></div></div>
            <div class="table-scroll"><table><thead><tr><th>Reference</th><th>Customer</th><th>Schedule</th><th>Status</th><th>Total</th></tr></thead><tbody>@forelse($reservations->take(6) as $reservation)<tr><td>{{ $reservation->reference }}</td><td>{{ $reservation->customer_name }}</td><td>{{ $reservation->reservation_at->format('M d, Y H:i') }}</td><td><span class="reservation-status {{ $reservation->status }}">{{ ucfirst($reservation->status) }}</span></td><td>&#8369;{{ number_format($reservation->total_amount,2) }}</td></tr>@empty<tr><td colspan="5" class="empty-row">No reservation requests in this period.</td></tr>@endforelse</tbody></table></div>
        </article>
        <article class="dashboard-card requested-food">
            <div class="card-heading"><div><p>RESERVATION FOOD</p><h2>Top requested food</h2></div></div>
            <div class="rank-list">@forelse($topRequestedProducts as $item)<div><span class="rank-number">{{ $loop->iteration }}</span><span><strong>{{ $item->product?->name ?? 'Menu item' }}</strong><small>{{ $item->units }} requested</small></span><b>&#8369;{{ number_format($item->value,2) }}</b></div>@empty<p class="empty-row">No approved food requests yet.</p>@endforelse</div>
        </article>

        <article class="dashboard-card movement-card">
            <div class="card-heading"><div><p>AUDIT TRAIL</p><h2>Stock movement report</h2></div><a href="{{ route('inventory.index') }}">View all stock</a></div>
            <div class="table-scroll"><table><thead><tr><th>Date</th><th>Product</th><th>Movement</th><th>Qty</th><th>Before</th><th>After</th><th>Recorded by</th></tr></thead><tbody>@forelse($stockMovements as $movement)<tr><td>{{ $movement->created_at->format('M d, H:i') }}</td><td>{{ $movement->product?->name ?? 'Deleted product' }}</td><td>{{ str($movement->type)->replace('_',' ')->title() }}</td><td>{{ $movement->quantity }}</td><td>{{ $movement->stock_before }}</td><td>{{ $movement->stock_after }}</td><td>{{ $movement->user?->name ?? 'System' }}</td></tr>@empty<tr><td colspan="7" class="empty-row">No stock movements in this period.</td></tr>@endforelse</tbody></table></div>
        </article>
    </section>
</div>
</main>
</div>
<style>
.report-workspace{background:#f3f4ef!important}.report-dashboard{max-width:1500px;margin:auto}.report-topbar{display:flex;justify-content:space-between;align-items:center;gap:24px;margin-bottom:22px}.report-topbar p,.card-heading p{margin:0 0 6px;color:#838d00;font-size:10px;font-weight:850;letter-spacing:.14em}.report-topbar h1{font-size:30px;margin:0;letter-spacing:-.035em}.report-topbar>div>span{display:block;color:#757b72;font-size:13px;margin-top:5px}.period-tabs{display:flex;padding:4px;background:#e7e9e1;border-radius:12px}.period-tabs a,.period-tabs button{height:38px;padding:0 15px;border:0;border-radius:9px;background:transparent;color:#686e65;text-decoration:none;display:grid;place-items:center;font:700 12px inherit;cursor:pointer}.period-tabs .active{background:#171817;color:#fff;box-shadow:0 5px 13px #17181728}.report-filter{display:none;grid-template-columns:repeat(3,minmax(150px,1fr)) auto auto;gap:12px;align-items:end;background:#fff;border:1px solid #dedfd8;border-radius:14px;padding:15px;margin-bottom:18px}.report-filter.open{display:grid}.report-filter label{display:block;font-size:11px;font-weight:800;margin-bottom:5px}.report-filter .control{width:100%;min-height:42px}.report-filter>a{min-height:42px;display:grid;place-items:center;color:#666c63}.report-primary{min-height:42px;border:0;border-radius:10px;padding:0 18px;background:#171817;color:#fff;font-weight:800}.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:14px}.metric-card,.dashboard-card{background:#fff;border:1px solid #e2e3dd;border-radius:17px;box-shadow:0 8px 28px rgba(26,29,24,.045)}.metric-card{position:relative;min-height:142px;padding:20px;overflow:hidden}.metric-card:after{content:"";position:absolute;inset:auto -20px -45px auto;width:105px;height:105px;border-radius:50%;background:#f1f2e8}.metric-card>span{display:block;color:#71766e;font-size:12px;margin:4px 0 6px}.metric-card>strong{display:block;font-size:25px;letter-spacing:-.025em}.metric-card small{display:block;color:#7e837b;font-size:10px;margin-top:8px}.metric-card small.up{color:#28765e}.metric-card small.down{color:#c24949}.metric-icon{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:#e9ecd2;color:#626b00;font-weight:900}.metric-card.orders .metric-icon{background:#e7e9fd;color:#5258c5}.metric-card.cash .metric-icon{background:#e3f3ee;color:#19755b}.metric-card.gcash .metric-icon{background:#e5f0ff;color:#1762be}.dashboard-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,.72fr);gap:14px}.dashboard-card{min-width:0;padding:20px}.card-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:16px}.card-heading h2{font-size:17px;margin:0}.card-heading>span,.card-heading>a{color:#6f756c;font-size:11px}.sales-chart-card{min-height:340px}.chart-wrap{position:relative;height:230px}.chart-wrap canvas{width:100%;height:100%;cursor:crosshair}.chart-tooltip{position:absolute;z-index:5;pointer-events:none;min-width:145px;padding:10px 12px;border-radius:10px;background:#171817;color:#fff;box-shadow:0 10px 28px rgba(0,0,0,.22);font-size:11px;line-height:1.55;transform:translate(-50%,-110%)}.chart-tooltip b{display:block;margin-bottom:3px}.chart-tooltip span{display:flex;justify-content:space-between;gap:18px}.chart-tooltip i{width:7px;height:7px;border-radius:2px;display:inline-block;margin-right:5px}.chart-empty{position:absolute;inset:0;place-items:center;color:#7b8077}.chart-legend{display:flex;justify-content:center;gap:22px;margin-top:9px;font-size:11px;color:#6f756d}.chart-legend span,.payment-breakdown span{display:flex;align-items:center;gap:6px}.cash-dot,.gcash-dot{width:9px;height:9px;border-radius:3px;background:#afb91a}.gcash-dot{background:#4499c5}.donut{width:160px;aspect-ratio:1;border-radius:50%;margin:6px auto 20px;background:conic-gradient(#afb91a 0 calc(var(--cash)*1%),#4499c5 calc(var(--cash)*1%) 100%);display:grid;place-items:center}.donut:before{content:"";grid-area:1/1;width:104px;aspect-ratio:1;border-radius:50%;background:#fff}.donut>div{grid-area:1/1;z-index:1;text-align:center;display:grid}.donut strong{font-size:25px}.donut span{font-size:10px;color:#777d74}.payment-breakdown{display:grid;gap:10px}.payment-breakdown>div{display:flex;justify-content:space-between;font-size:12px}.recent-sales,.reservation-card,.movement-card{grid-column:1}.stock-summary,.requested-food{grid-column:2}.table-scroll{overflow:auto;scrollbar-width:none}.table-scroll::-webkit-scrollbar{display:none}.dashboard-card table{min-width:650px!important}.dashboard-card th{background:#f4f5f0!important;font-size:9px!important;padding:9px!important}.dashboard-card td{padding:10px 9px!important;font-size:11px}.dashboard-card td small{display:block;color:#858a82;margin-top:3px}.table-action{color:#606900;font-weight:800}.payment-badge,.reservation-status{display:inline-flex;padding:4px 7px;border-radius:999px;font-size:9px;font-weight:850;letter-spacing:.04em}.payment-badge.cash{background:#e8f4ef;color:#247159}.payment-badge.gcash{background:#e7f0ff;color:#1762b9}.stacked-metrics{display:grid;grid-template-columns:1fr 1fr;gap:10px}.stacked-metrics>div{padding:16px;background:#f6f7f2;border-radius:12px}.stacked-metrics span,.reservation-metrics span{display:block;color:#747a71;font-size:10px}.stacked-metrics strong{display:block;font-size:24px;margin:6px 0 2px}.stacked-metrics small{color:#858a82;font-size:9px}.stacked-metrics .attention{background:#fff0ed}.best-products{grid-column:1}.stock-alerts{grid-column:2}.rank-list{display:grid}.rank-list>div{display:grid;grid-template-columns:32px minmax(0,1fr) auto;align-items:center;gap:10px;padding:10px 0;border-top:1px solid #eceee7}.rank-number{width:27px;height:27px;border-radius:8px;background:#eff1df;color:#747d00;display:grid;place-items:center;font-size:10px;font-weight:900}.rank-list>div>span:nth-child(2){display:grid}.rank-list strong{font-size:12px}.rank-list small{font-size:10px;color:#7c8179;margin-top:2px}.rank-list b{font-size:12px}.alert-list{display:grid}.alert-list>div{display:flex;justify-content:space-between;gap:15px;padding:10px 0;border-top:1px solid #eceee7;font-size:11px}.danger{color:#c13c3c}.healthy{color:#34745c}.reservation-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:15px}.reservation-metrics>div{background:#f5f6ef;border-radius:11px;padding:13px}.reservation-metrics strong{display:block;font-size:19px;margin-top:5px}.reservation-status{background:#eff0ec}.reservation-status.confirmed,.reservation-status.completed{background:#e4f3e8;color:#28704d}.reservation-status.cancelled{background:#fff0f0;color:#bd4040}.movement-card{grid-column:1/-1}.empty-row{text-align:center!important;color:#7d827a!important;padding:25px!important}
@media(max-width:1150px){.metric-grid{grid-template-columns:repeat(2,1fr)}.dashboard-grid{grid-template-columns:1fr}.dashboard-grid>*{grid-column:1!important}.payment-card-report{min-height:300px}.donut{width:145px}.stacked-metrics{grid-template-columns:repeat(4,1fr)}}
@media(max-width:760px){.report-topbar{align-items:flex-start;flex-direction:column}.period-tabs{width:100%;overflow:auto}.period-tabs a,.period-tabs button{flex:1;white-space:nowrap}.report-filter,.report-filter.open{grid-template-columns:1fr}.metric-grid{grid-template-columns:1fr 1fr}.stacked-metrics,.reservation-metrics{grid-template-columns:1fr 1fr}.report-workspace{padding-bottom:85px!important}}
@media(max-width:480px){.metric-grid{grid-template-columns:1fr}.metric-card{min-height:125px}.stacked-metrics,.reservation-metrics{grid-template-columns:1fr}.dashboard-card{padding:16px}.report-topbar h1{font-size:26px}}
</style>
<script>
(() => {
    const toggle=document.querySelector('[data-filter-toggle]'),form=document.querySelector('[data-filter-form]');
    toggle?.addEventListener('click',()=>form?.classList.toggle('open'));
    const canvas=document.getElementById('sales-chart'); if(!canvas)return;
    const data=@json($chart),ctx=canvas.getContext('2d'),empty=canvas.parentElement.querySelector('.chart-empty'),tooltip=canvas.parentElement.querySelector('.chart-tooltip');
    let geometry=null;
    const draw=()=>{
        const rect=canvas.getBoundingClientRect(),ratio=Math.min(devicePixelRatio||1,2);
        canvas.width=Math.round(rect.width*ratio);canvas.height=Math.round(rect.height*ratio);ctx.setTransform(ratio,0,0,ratio,0,0);
        const w=rect.width,h=rect.height,p={l:46,r:12,t:16,b:31},cw=w-p.l-p.r,ch=h-p.t-p.b,max=Math.max(...data.cash,...data.gcash,0);
        ctx.clearRect(0,0,w,h); if(!max){empty.hidden=false;empty.style.display='grid';return} empty.hidden=true;empty.style.display='none';
        ctx.font='10px system-ui';ctx.fillStyle='#858a82';ctx.strokeStyle='#e7e9e2';ctx.lineWidth=1;
        for(let i=0;i<=4;i++){const y=p.t+ch*(i/4);ctx.beginPath();ctx.moveTo(p.l,y);ctx.lineTo(w-p.r,y);ctx.stroke();const value=max*(1-i/4);ctx.fillText(value>=1000?(value/1000).toFixed(1)+'k':Math.round(value),4,y+3)}
        const count=data.labels.length,slot=cw/Math.max(count,1),bar=Math.max(2,Math.min(13,slot*.29));geometry={p,cw,ch,slot,count,w,h};
        data.labels.forEach((label,i)=>{const center=p.l+slot*i+slot/2,cashH=(data.cash[i]/max)*ch,gcashH=(data.gcash[i]/max)*ch;
            ctx.fillStyle='#afb91a';ctx.fillRect(center-bar-1,p.t+ch-cashH,bar,cashH);ctx.fillStyle='#4499c5';ctx.fillRect(center+1,p.t+ch-gcashH,bar,gcashH);
            const show=count<=12||i%Math.ceil(count/10)===0||i===count-1;if(show){ctx.fillStyle='#777d74';ctx.textAlign='center';ctx.fillText(label,center,h-8)}
        });ctx.textAlign='start';
    };
    const money=value=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(value||0);
    canvas.addEventListener('mousemove',event=>{
        if(!geometry||!data.labels.length)return;
        const rect=canvas.getBoundingClientRect(),x=event.clientX-rect.left,y=event.clientY-rect.top,{p,cw,ch,slot,count}=geometry;
        if(x<p.l||x>p.l+cw||y<p.t||y>p.t+ch){tooltip.hidden=true;return}
        const index=Math.max(0,Math.min(count-1,Math.floor((x-p.l)/slot))),center=p.l+slot*index+slot/2;
        tooltip.innerHTML=`<b>${data.labels[index]}</b><span><em><i style="background:#afb91a"></i>Cash</em><strong>${money(data.cash[index])}</strong></span><span><em><i style="background:#4499c5"></i>GCash</em><strong>${money(data.gcash[index])}</strong></span><span><em>Total</em><strong>${money((data.cash[index]||0)+(data.gcash[index]||0))}</strong></span>`;
        tooltip.hidden=false;tooltip.style.left=`${Math.max(80,Math.min(rect.width-80,center))}px`;tooltip.style.top=`${Math.max(80,y)}px`;
    });
    canvas.addEventListener('mouseleave',()=>tooltip.hidden=true);
    draw();new ResizeObserver(draw).observe(canvas);
})();
</script>
@endsection
