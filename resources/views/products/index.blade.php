@extends('layouts.app')
@section('title', 'Product Management')
@section('content')
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard">
    <header class="topbar product-management-header">
        <div class="product-page-heading"><h1 style="font-size:24px">Product management</h1><span class="muted">Add and update cashier products</span></div>
        <div class="product-header-actions">
        <form method="GET" action="{{ route('products.index') }}" class="product-search-form" aria-label="Search products">
            <div class="product-search-control">
                <div class="product-search-field">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                    <input id="product-search" name="search" type="search" value="{{ $search }}" placeholder="Search products or categories" maxlength="100" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="product-search-options" aria-expanded="false" aria-label="Search products or categories">
                    <button class="product-search-dropdown" type="button" aria-label="Show products and categories" title="Show products and categories" aria-controls="product-search-options" aria-expanded="false"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"></path></svg></button>
                </div>
                <div id="product-search-options" class="product-search-options" role="listbox" hidden>
                    @foreach($searchCategories as $category)<button type="button" class="product-search-option" role="option" data-search-value="{{ $category }}"><span>{{ $category }}</span><small>Category</small></button>@endforeach
                    @foreach($searchProducts as $productName)<button type="button" class="product-search-option" role="option" data-search-value="{{ $productName }}"><span>{{ $productName }}</span><small>Product</small></button>@endforeach
                    <p class="product-search-empty" hidden>No matching products or categories</p>
                </div>
            </div>
            <button class="product-search-button" type="submit">Search</button>
            @if($search !== '')<a class="product-search-clear" href="{{ route('products.index') }}" aria-label="Clear search" title="Clear search"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"></path></svg></a>@endif
        </form>
        @if(auth()->user()->hasRole('super_admin', 'admin'))<button id="product-create-toggle" class="product-create-toggle" type="button" aria-controls="product-create-panel" aria-expanded="{{ old('form_context') === 'create' ? 'true' : 'false' }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg><span>{{ old('form_context') === 'create' ? 'Close form' : 'Add product' }}</span></button>@endif
        </div>
    </header>
    @if($search !== '')<p class="product-search-summary">{{ $products->count() }} {{ Str::plural('product', $products->count()) }} found for “{{ $search }}”</p>@endif
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" style="background:#fff0f0;padding:12px;border-radius:9px;margin-bottom:18px">{{ $errors->first() }}</div>@endif
    @if(auth()->user()->hasRole('super_admin', 'admin'))<section id="product-create-panel" class="welcome product-create-panel" {{ old('form_context') === 'create' ? '' : 'hidden' }}>
        <h2>New product</h2>
        <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">@csrf
            <input type="hidden" name="form_context" value="create">
            <div class="product-create-grid">
                <div class="field"><label for="name">Product name</label><input class="control" id="name" name="name" value="{{ old('name') }}" required></div>
                <div class="field"><label for="category">Category</label><input class="control" id="category" name="category" value="{{ old('category') }}" placeholder="e.g. Starters" required></div>
                <div class="field"><label for="price">Price (₱)</label><input class="control" id="price" name="price" type="number" min="0.01" step="0.01" value="{{ old('price') }}" required></div>
                <div class="field"><label for="stock">Stock</label><input class="control" id="stock" name="stock" type="number" min="0" value="{{ old('stock', 0) }}" required></div>
            </div>
            <div class="field"><label for="description">Description</label><textarea class="control" id="description" name="description" rows="2">{{ old('description') }}</textarea></div>
            <div class="field"><label for="image">Product picture <span style="font-weight:400;color:#687286">(JPG, PNG or WebP, up to 2 MB)</span></label><input class="control" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"></div>
            <label class="check"><input name="active" type="checkbox" value="1" checked> Show this product on the cashier page</label>
            <button class="button" style="width:auto;padding-inline:26px" type="submit">Add product</button>
        </form>
    </section>@endif
    <section style="display:grid;gap:14px">
        @forelse($products->groupBy('category') as $category => $items)
        <h2 style="margin:20px 0 0;border-bottom:2px solid #171817;padding-bottom:8px">{{ $category }}</h2>
        @foreach($items as $product)
        <article class="welcome" style="padding:22px">
            <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">@csrf @method('PUT')
                @if($imageUrl = $product->imageUrl())
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px"><img src="{{ $imageUrl }}" alt="{{ $product->name }}" style="width:86px;height:86px;object-fit:cover;border-radius:12px"><label class="check" style="margin:0"><input name="remove_image" type="checkbox" value="1"> Remove current picture</label></div>
                @endif
                <div style="display:grid;grid-template-columns:2fr 1.4fr 1fr 1fr auto;gap:12px;align-items:end">
                    <div><label>Name</label><input class="control" name="name" value="{{ $product->name }}" required></div>
                    <div><label>Category</label><input class="control" name="category" value="{{ $product->category }}" required></div>
                    <div><label>Price (₱)</label><input class="control" name="price" type="number" min="0.01" step="0.01" value="{{ $product->price }}" required></div>
                    <div><label>Stock</label><input class="control" name="stock" type="number" min="0" value="{{ $product->stock }}" required></div>
                    <button class="button" style="width:auto" type="submit">Save</button>
                </div>
                <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end;margin-top:12px"><div><label>Description</label><input class="control" name="description" value="{{ $product->description }}"></div><label class="check" style="margin:0 0 11px"><input name="active" type="checkbox" value="1" {{ $product->active ? 'checked' : '' }}> Visible</label></div>
                <div style="margin-top:12px"><label>Replace picture</label><input class="control" name="image" type="file" accept="image/jpeg,image/png,image/webp"></div>
            </form>
            @if(auth()->user()->hasRole('super_admin'))<form method="POST" action="{{ route('products.destroy', $product) }}" style="margin-top:12px" onsubmit="return confirm('Permanently delete this product?')">@csrf @method('DELETE')<button class="logout" style="color:#b42318" type="submit">Delete</button></form>@endif
        </article>
        @endforeach
        @empty <div class="welcome">{{ $search !== '' ? 'No products match your search.' : 'No products yet. Add your first product above.' }}</div> @endforelse
    </section>
</div></main></div>
<style>
.product-management-header{gap:28px}
.product-page-heading{flex:0 0 auto}
.product-header-actions{min-width:0;flex:1;display:flex;align-items:center;justify-content:flex-end;gap:10px}
.product-search-form{width:min(650px,100%);display:flex;align-items:center;justify-content:flex-end;gap:10px}
.product-search-control{position:relative;min-width:0;flex:1}
.product-search-field{min-width:0;flex:1;height:50px;display:flex;align-items:center;gap:11px;padding:0 16px;border:1px solid #d2d5cb;border-radius:8px;background:#fff;transition:border-color .15s,box-shadow .15s}
.product-search-field:focus-within{border-color:#737d00;box-shadow:0 0 0 3px rgba(175,185,26,.17)}
.product-search-field svg{width:21px;height:21px;flex:0 0 21px;fill:none;stroke:#62675f;stroke-width:2;stroke-linecap:round}
.product-search-field input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:#171817;font-family:inherit;font-size:15px;font-weight:500}
.product-search-field input::placeholder{color:#858a82}
.product-search-dropdown{width:32px;height:32px;flex:0 0 32px;display:grid;place-items:center;border:0;border-radius:7px;background:transparent;color:#62675f;cursor:pointer}
.product-search-dropdown:hover{background:#eff0ea;color:#171817}
.product-search-dropdown svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.product-search-dropdown[aria-expanded="true"] svg{transform:rotate(180deg)}
.product-search-options{position:absolute;z-index:30;top:calc(100% + 7px);left:0;right:0;max-height:310px;overflow-y:auto;padding:7px;border:1px solid #dfe1da;border-radius:8px;background:#fff;box-shadow:0 12px 28px rgba(23,24,23,.14)}
.product-search-option{width:100%;min-height:42px;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:9px 11px;border:0;border-radius:6px;background:transparent;color:#252724;font-family:inherit;font-size:14px;text-align:left;cursor:pointer}
.product-search-option:hover,.product-search-option.is-active{background:#eff0ea;color:#171817}
.product-search-option small{color:#81867e;font-size:11px;white-space:nowrap}
.product-search-empty{margin:0;padding:14px 11px;color:#777d74;font-size:13px;text-align:center}
.product-search-button{height:50px;padding:0 25px;border:0;border-radius:8px;background:#171817;color:#fff;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer}
.product-search-button:hover{background:#30322e}
.product-search-clear{width:44px;height:44px;display:grid;place-items:center;border-radius:8px;color:#555b52;text-decoration:none}
.product-search-clear:hover{background:#e8e9e3;color:#171817}
.product-search-clear svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}
.product-search-summary{margin:-12px 0 20px;color:#6d736a;font-size:13px;text-align:right}
.product-create-toggle{height:50px;flex:0 0 auto;display:flex;align-items:center;gap:8px;padding:0 18px;border:0;border-radius:8px;background:#171817;color:#fff;font-family:inherit;font-size:14px;font-weight:800;cursor:pointer;white-space:nowrap}
.product-create-toggle:hover{background:#30322e}
.product-create-toggle svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;transition:transform .18s}
.product-create-toggle[aria-expanded="true"] svg{transform:rotate(45deg)}
.product-create-panel{margin:0 0 22px;padding:26px}
.product-create-panel h2{margin:0 0 20px;font-size:20px}
.product-create-grid{display:grid;grid-template-columns:2fr 1.4fr 1fr 1fr;gap:14px}
@media(max-width:1100px){.product-management-header{display:grid;align-items:start}.product-header-actions,.product-search-form{width:100%;justify-content:stretch}.product-search-summary{margin-top:-10px;text-align:left}}
@media(max-width:820px){.product-create-grid{grid-template-columns:1fr 1fr}}
@media(max-width:640px){.product-header-actions{display:grid}.product-search-form{display:grid;grid-template-columns:1fr auto auto}.product-search-control{grid-column:1/-1}.product-search-button{padding-inline:20px}.product-create-toggle{width:100%;justify-content:center}}
@media(max-width:520px){.product-create-grid{grid-template-columns:1fr}}
</style>
<script>
(() => {
    const input = document.getElementById('product-search');
    const dropdown = document.querySelector('.product-search-dropdown');
    const panel = document.getElementById('product-search-options');
    const empty = panel?.querySelector('.product-search-empty');
    const options = [...(panel?.querySelectorAll('.product-search-option') ?? [])];
    let activeIndex = -1;
    if (!input || !dropdown || !panel) return;

    const visibleOptions = () => options.filter(option => !option.hidden);
    const setOpen = open => {
        panel.hidden = !open;
        input.setAttribute('aria-expanded', String(open));
        dropdown.setAttribute('aria-expanded', String(open));
        if (!open) setActive(-1);
    };
    const setActive = index => {
        const visible = visibleOptions();
        options.forEach(option => option.classList.remove('is-active'));
        activeIndex = visible.length ? Math.max(-1, Math.min(index, visible.length - 1)) : -1;
        if (activeIndex >= 0) {
            visible[activeIndex].classList.add('is-active');
            visible[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    };
    const filterOptions = () => {
        const query = input.value.trim().toLocaleLowerCase();
        let matches = 0;
        options.forEach(option => {
            option.hidden = query !== '' && !option.dataset.searchValue.toLocaleLowerCase().includes(query);
            if (!option.hidden) matches++;
        });
        if (empty) empty.hidden = matches > 0;
        setActive(-1);
    };
    const selectOption = option => {
        input.value = option.dataset.searchValue;
        setOpen(false);
        input.form?.requestSubmit();
    };

    input.addEventListener('focus', () => { filterOptions(); setOpen(true); });
    input.addEventListener('input', () => { filterOptions(); setOpen(true); });
    dropdown.addEventListener('click', () => {
        const opening = panel.hidden;
        if (opening) filterOptions();
        setOpen(opening);
        input.focus();
    });
    options.forEach(option => option.addEventListener('click', () => selectOption(option)));
    input.addEventListener('keydown', event => {
        const visible = visibleOptions();
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (panel.hidden) setOpen(true);
            setActive(event.key === 'ArrowDown' ? activeIndex + 1 : (activeIndex <= 0 ? visible.length - 1 : activeIndex - 1));
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            selectOption(visible[activeIndex]);
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.product-search-control')) setOpen(false);
    });
})();
</script>
@if(auth()->user()->hasRole('super_admin', 'admin'))<script>
(() => {
    const toggle = document.getElementById('product-create-toggle');
    const panel = document.getElementById('product-create-panel');
    if (!toggle || !panel) return;

    toggle.addEventListener('click', () => {
        const opening = panel.hidden;
        panel.hidden = !opening;
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        toggle.querySelector('span').textContent = opening ? 'Close form' : 'Add product';
        if (opening) panel.querySelector('input:not([type="hidden"])')?.focus();
    });
})();
</script>@endif
@endsection
