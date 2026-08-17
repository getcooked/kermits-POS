@extends('layouts.app')
@section('title', 'Product Management')
@section('content')
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard">
    <header class="topbar"><div><h1 style="font-size:24px">Product management</h1><span class="muted">Add and update cashier products</span></div><a class="logout" style="text-decoration:none;color:inherit" href="{{ route('dashboard') }}">Dashboard</a></header>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" style="background:#fff0f0;padding:12px;border-radius:9px;margin-bottom:18px">{{ $errors->first() }}</div>@endif
    @if(auth()->user()->hasRole('super_admin'))<section class="welcome" style="padding:26px;margin-bottom:22px">
        <h2 style="margin:0 0 20px">Add a product</h2>
        <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">@csrf
            <div style="display:grid;grid-template-columns:2fr 1.4fr 1fr 1fr;gap:14px">
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
    <section class="product-search-panel" aria-label="Search products">
        <form method="GET" action="{{ route('products.index') }}" class="product-search-form">
            <div class="product-search-field">
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                <label class="sr-only" for="product-search">Search by product name or category</label>
                <input id="product-search" name="search" type="search" value="{{ $search }}" placeholder="Search product or category" maxlength="100">
            </div>
            <button class="product-search-button" type="submit">Search</button>
            @if($search !== '')<a class="product-search-clear" href="{{ route('products.index') }}">Clear</a>@endif
        </form>
        @if($search !== '')<p class="product-search-summary">{{ $products->count() }} {{ Str::plural('product', $products->count()) }} found for “{{ $search }}”</p>@endif
    </section>
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
.product-search-panel{margin-bottom:22px;padding:16px 18px;background:#fff;border:1px solid #daddd1;border-radius:8px}
.product-search-form{display:flex;align-items:center;gap:10px}
.product-search-field{min-width:0;flex:1;height:48px;display:flex;align-items:center;gap:11px;padding:0 14px;border:1px solid #cfd2c8;border-radius:8px;background:#fff;transition:border-color .15s,box-shadow .15s}
.product-search-field:focus-within{border-color:#737d00;box-shadow:0 0 0 3px rgba(175,185,26,.17)}
.product-search-field svg{width:21px;height:21px;flex:0 0 21px;fill:none;stroke:#62675f;stroke-width:2;stroke-linecap:round}
.product-search-field input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:#171817;font:inherit}
.product-search-field input::placeholder{color:#858a82}
.product-search-button{height:48px;padding:0 24px;border:0;border-radius:8px;background:#171817;color:#fff;font:700 15px inherit;cursor:pointer}
.product-search-button:hover{background:#30322e}
.product-search-clear{height:48px;padding:0 12px;display:grid;place-items:center;color:#555b52;font-weight:700;text-decoration:none}
.product-search-summary{margin:11px 0 0;color:#6d736a;font-size:13px}
@media(max-width:640px){.product-search-form{display:grid;grid-template-columns:1fr auto}.product-search-field{grid-column:1/-1}.product-search-button{padding-inline:20px}.product-search-clear{padding-inline:8px}}
</style>
@endsection
