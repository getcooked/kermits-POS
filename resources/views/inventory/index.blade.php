@extends('layouts.app')
@section('title','Inventory')
@section('content')
@php($lowStockProducts = $products->filter(fn ($product) => $product->stock <= \App\Models\Product::LOW_STOCK_THRESHOLD)->count())
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard">
<header class="topbar"><div><h1 style="font-size:26px">Inventory</h1></div><div class="inventory-search"><x-search-field id="inventory-search" placeholder="Search categories or products" data-inventory-search /><div class="inventory-search-results" data-inventory-results hidden></div></div></header>
@if(session('status'))<div class="notice">{{ session('status') }}</div>@endif @if($errors->any())<div class="error" style="background:#fff0f0;padding:12px;border-radius:9px;margin-bottom:18px">{{ $errors->first() }}</div>@endif
<section style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px"><div class="welcome" style="padding:20px"><span class="muted">Products</span><h2>{{ $products->count() }}</h2></div><div class="welcome" style="padding:20px"><span class="muted">Total Units</span><h2>{{ number_format($totalUnits) }}</h2></div><div class="welcome" style="padding:20px"><span class="muted">Low Stock</span><h2 style="color:{{ $lowStock?'#c42b2b':'inherit' }}">{{ $lowStock }}</h2></div></section>
@php($categoryGroups = $products->groupBy(fn ($product) => $product->category ?: 'Uncategorized'))
<section class="welcome" style="padding:24px;margin-bottom:20px"><div class="inventory-heading"><h2 style="margin:0">Stocks</h2><button type="button" class="low-stock-toggle" aria-pressed="false" data-low-stock-toggle>Low Stock <span>{{ $lowStockProducts }}</span></button></div><div class="category-chips"><button type="button" class="category-chip" aria-pressed="true" data-category-chip="">All</button>@foreach($categoryGroups->keys() as $category)<button type="button" class="category-chip" aria-pressed="false" data-category-chip="{{ $category }}">{{ $category }}</button>@endforeach</div>@foreach($categoryGroups as $category => $groupProducts)@php($groupLow = $groupProducts->filter(fn ($product) => $product->stock <= \App\Models\Product::LOW_STOCK_THRESHOLD)->count())<div class="inventory-group" data-inventory-group><div class="inventory-group-heading"><h3>{{ $category }}</h3><span class="muted">{{ $groupProducts->count() }} {{ Str::plural('product', $groupProducts->count()) }}@if($groupLow) · <b>{{ $groupLow }} low</b>@endif</span></div><div class="inventory-grid">@foreach($groupProducts as $product)<article class="inventory-item" data-inventory-item data-name="{{ $product->name }}" data-category="{{ $category }}" data-low="{{ $product->stock<=\App\Models\Product::LOW_STOCK_THRESHOLD?'1':'0' }}">@if($imageUrl = $product->imageUrl())<img class="product-image" src="{{ $imageUrl }}" alt="">@else<div class="inventory-placeholder">{{ strtoupper(substr($product->name,0,1)) }}</div>@endif<div class="inventory-info"><h3>{{ $product->name }}</h3><span class="stock-badge {{ $product->stock<=\App\Models\Product::LOW_STOCK_THRESHOLD?'low':'' }}">{{ $product->stock }} units</span></div><form method="POST" action="{{ route('inventory.update',$product) }}" data-ajax-form data-ajax-target=".dashboard" data-ajax-loading="Updating...">@csrf<div class="inventory-controls"><select class="control" name="type"><option value="stock_in">Stock in</option><option value="stock_out">Stock out</option></select><input class="control" name="quantity" type="number" min="1" max="50" placeholder="Quantity (max 50)" required><input class="control" name="note" placeholder="Note (optional)"><button class="button">Update</button></div></form></article>@endforeach</div></div>@endforeach<p class="muted inventory-empty" data-inventory-empty hidden>No products match your filter.</p></section>
</div></main></div>
@push('styles')
<style>.inventory-search{position:relative;width:min(380px,100%)}.inventory-search-results{position:absolute;top:calc(100% + 6px);right:0;left:0;z-index:20;max-height:360px;overflow:auto;background:#fff;border:1px solid #e0e3d9;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.08);padding:6px}.inventory-search-results h4{margin:8px 10px 4px;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#687286}.inventory-search-results button{display:flex;justify-content:space-between;gap:10px;width:100%;padding:9px 10px;border:0;border-radius:8px;background:none;text-align:left;font:inherit;cursor:pointer}.inventory-search-results button:hover{background:#f3f4ec}.inventory-search-results small{color:#687286}.inventory-search-results p{margin:10px;color:#687286}.inventory-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}.low-stock-toggle{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border:1px solid #efc8c5;border-radius:999px;background:#fff;color:#c42b2b;font:inherit;font-weight:700;cursor:pointer}.low-stock-toggle span{min-width:22px;padding:1px 7px;border-radius:999px;background:#fff0f0;font-size:12px;text-align:center}.low-stock-toggle[aria-pressed="true"]{background:#c42b2b;border-color:#c42b2b;color:#fff}.low-stock-toggle[aria-pressed="true"] span{background:rgba(255,255,255,.2)}.category-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px}.category-chip{padding:7px 13px;border:1px solid #e0e3d9;border-radius:999px;background:#fff;color:inherit;font:inherit;font-size:13px;cursor:pointer}.category-chip:hover{border-color:#747d00}.category-chip[aria-pressed="true"]{background:#1c1c1c;border-color:#1c1c1c;color:#fff}.inventory-group+.inventory-group{margin-top:26px}.inventory-group[hidden]{display:none}.inventory-group-heading{display:flex;align-items:baseline;gap:12px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #e7eaf0}.inventory-group-heading h3{margin:0;font-size:18px}.inventory-group-heading span{font-size:13px}.inventory-group-heading b{color:#c42b2b;font-weight:700}.inventory-item.is-highlighted{border-color:#747d00;box-shadow:0 0 0 3px #eff1df}.inventory-empty{margin:8px 0 0}.inventory-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.inventory-item{border:1px solid #e0e3d9;border-radius:14px;padding:16px;display:grid;grid-template-columns:58px 1fr;gap:12px}.inventory-item[hidden]{display:none}.inventory-item>img,.inventory-placeholder{width:58px;height:58px;border-radius:10px;object-fit:cover}.inventory-placeholder{display:grid;place-items:center;background:#eff1df;color:#747d00;font-weight:800}.inventory-info h3{margin:3px 0 8px}.stock-badge{font-size:16px;font-weight:600;color:#267444}.stock-badge.low{color:#c42b2b}.inventory-item form{grid-column:1/-1}.inventory-controls{display:grid;grid-template-columns:1fr .7fr 1fr auto;gap:8px}.inventory-controls .button{width:auto}@media(max-width:1100px){.inventory-grid{grid-template-columns:1fr}.inventory-controls{grid-template-columns:1fr 1fr}}@media(max-width:520px){.inventory-controls{grid-template-columns:1fr}.inventory-controls .button{width:100%}}</style>
@endpush
@push('scripts')
<script>
(() => {
    const state = { query: new URLSearchParams(window.location.search).get('search') ?? '', category: null, lowOnly: false };
    const normalize = value => (value || '').toLowerCase().trim();

    const applyFilters = () => {
        const query = normalize(state.query);
        let visible = 0;
        document.querySelectorAll('[data-inventory-item]').forEach(item => {
            const matchesCategory = !state.category || item.dataset.category === state.category;
            const matchesQuery = !query || normalize(item.dataset.name).includes(query) || normalize(item.dataset.category).includes(query);
            const matchesLow = !state.lowOnly || item.dataset.low === '1';
            item.hidden = !(matchesCategory && matchesQuery && matchesLow);
            if (!item.hidden) visible++;
        });
        document.querySelectorAll('[data-inventory-group]').forEach(group => {
            group.hidden = !group.querySelector('[data-inventory-item]:not([hidden])');
        });
        document.querySelectorAll('[data-category-chip]').forEach(chip => {
            chip.setAttribute('aria-pressed', chip.dataset.categoryChip === (state.category ?? '') ? 'true' : 'false');
        });
        const empty = document.querySelector('[data-inventory-empty]');
        if (empty) empty.hidden = visible > 0;
        const toggle = document.querySelector('[data-low-stock-toggle]');
        if (toggle) toggle.setAttribute('aria-pressed', state.lowOnly ? 'true' : 'false');
        const input = document.querySelector('[data-inventory-search]');
        if (input && input !== document.activeElement) input.value = state.query;
    };

    const renderResults = () => {
        const results = document.querySelector('[data-inventory-results]');
        const query = normalize(state.query);
        if (!results) return;
        results.replaceChildren();
        if (!query) { results.hidden = true; return; }

        const items = [...document.querySelectorAll('[data-inventory-item]')];
        const categories = [...new Set(items.map(item => item.dataset.category).filter(Boolean))]
            .filter(category => normalize(category).includes(query));
        const products = items.filter(item => normalize(item.dataset.name).includes(query)).slice(0, 10);

        const addGroup = (title, entries, build) => {
            if (!entries.length) return;
            const heading = document.createElement('h4');
            heading.textContent = title;
            results.append(heading);
            entries.forEach(entry => results.append(build(entry)));
        };
        const makeButton = (label, meta, onPick) => {
            const button = document.createElement('button');
            button.type = 'button';
            const name = document.createElement('span');
            name.textContent = label;
            const small = document.createElement('small');
            small.textContent = meta;
            button.append(name, small);
            button.addEventListener('mousedown', event => { event.preventDefault(); onPick(); });
            return button;
        };

        addGroup('Categories', categories, category => makeButton(category,
            `${items.filter(item => item.dataset.category === category).length} products`, () => {
                state.category = category;
                state.query = '';
                results.hidden = true;
                document.querySelector('[data-inventory-search]').blur();
                applyFilters();
            }));
        addGroup('Products', products, item => makeButton(item.dataset.name, item.dataset.category || 'Uncategorized', () => {
            state.category = null;
            state.query = item.dataset.name;
            results.hidden = true;
            document.querySelector('[data-inventory-search]').blur();
            applyFilters();
            item.scrollIntoView({ behavior: 'smooth', block: 'center' });
            item.classList.add('is-highlighted');
            setTimeout(() => item.classList.remove('is-highlighted'), 1600);
        }));

        if (!results.children.length) {
            const none = document.createElement('p');
            none.textContent = 'No matching categories or products.';
            results.append(none);
        }
        results.hidden = false;
    };

    document.addEventListener('input', event => {
        if (!event.target.matches('[data-inventory-search]')) return;
        state.query = event.target.value;
        state.category = null;
        applyFilters();
        renderResults();
    });
    document.addEventListener('focusin', event => {
        if (event.target.matches('[data-inventory-search]')) renderResults();
    });
    document.addEventListener('focusout', event => {
        if (!event.target.matches('[data-inventory-search]')) return;
        const results = document.querySelector('[data-inventory-results]');
        if (results) results.hidden = true;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && event.target.matches('[data-inventory-search]')) event.target.blur();
    });
    document.addEventListener('click', event => {
        const chip = event.target.closest('[data-category-chip]');
        if (chip) {
            state.category = chip.dataset.categoryChip || null;
            state.query = '';
            const input = document.querySelector('[data-inventory-search]');
            if (input) input.value = '';
            applyFilters();
            return;
        }
        if (!event.target.closest('[data-low-stock-toggle]')) return;
        state.lowOnly = !state.lowOnly;
        applyFilters();
    });
    document.addEventListener('ajax:content-updated', applyFilters);
    applyFilters();
})();
</script>
@endpush
@endsection
