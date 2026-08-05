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
    <section style="display:grid;gap:14px">
        @forelse($products->groupBy('category') as $category => $items)
        <h2 style="margin:20px 0 0;border-bottom:2px solid #171817;padding-bottom:8px">{{ $category }}</h2>
        @foreach($items as $product)
        <article class="welcome" style="padding:22px">
            <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">@csrf @method('PUT')
                @if($product->image_path)
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px"><img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" style="width:86px;height:86px;object-fit:cover;border-radius:12px"><label class="check" style="margin:0"><input name="remove_image" type="checkbox" value="1"> Remove current picture</label></div>
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
            @if(auth()->user()->hasRole('super_admin'))<form method="POST" action="{{ route('products.destroy', $product) }}" style="margin-top:12px" onsubmit="return confirm('Delete or hide this product?')">@csrf @method('DELETE')<button class="logout" style="color:#b42318" type="submit">Delete</button></form>@endif
        </article>
        @endforeach
        @empty <div class="welcome">No products yet. Add your first product above.</div> @endforelse
    </section>
</div></main></div>
@endsection
