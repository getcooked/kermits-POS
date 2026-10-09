@extends('layouts.app')
@section('title', 'Product Management')
@section('content')
@php
    $maxStock = \App\Models\Product::MAX_STOCK;
    $lowThreshold = \App\Models\Product::LOW_STOCK_THRESHOLD;
    $canCreate = auth()->user()->hasRole('super_admin', 'admin');
    $canDelete = auth()->user()->hasRole('super_admin');
    $groups = $products->groupBy(fn ($product) => $product->category ?: 'Uncategorized');
    $formContext = (string) old('form_context', '');
    $imageUrls = $products->mapWithKeys(fn ($product) => [$product->getKey() => $product->imageUrl()]);
    $editingProduct = str_starts_with($formContext, 'edit-')
        ? $products->firstWhere('id', (int) substr($formContext, 5))
        : null;
    $enabledProducts = $products->where('active', true);
    $stats = [
        'all' => ['label' => 'All Products', 'count' => $enabledProducts->count()],
        'enabled' => ['label' => 'Enabled', 'count' => $enabledProducts->count()],
        'disabled' => ['label' => 'Disabled', 'count' => $products->where('active', false)->count()],
        'low' => ['label' => 'Low Stock', 'count' => $enabledProducts->filter(fn ($product) => $product->stock <= $lowThreshold)->count()],
        'noimage' => ['label' => 'No Picture', 'count' => $enabledProducts->filter(fn ($product) => ! $imageUrls[$product->getKey()])->count()],
    ];
    $sortOptions = [
        'menu' => 'Menu order',
        'name' => 'Name (A–Z)',
        'price-asc' => 'Price: low to high',
        'price-desc' => 'Price: high to low',
        'stock-asc' => 'Stock: lowest first',
        'updated' => 'Recently updated',
    ];
    $placeholder = fn (string $name) => Str::upper(Str::substr($name, 0, 1));
    $position = 0;
    $stockState = fn ($product) => match (true) {
        $product->stock === 0 => ['class' => 'is-out', 'label' => 'Out of stock'],
        $product->stock <= $lowThreshold => ['class' => 'is-low', 'label' => 'Low · '.$product->stock.' left'],
        default => ['class' => 'is-ok', 'label' => $product->stock.' in stock'],
    };
@endphp
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard">
    <header class="topbar product-management-header">
        <div class="product-page-heading"><h1>Product Management</h1><p class="muted">Menu items, prices, stock and visibility</p></div>
        <div class="product-header-actions">
        <form method="GET" action="{{ route('products.index') }}" class="product-search-form" aria-label="Search products">
            <div class="product-search-control">
                <x-search-field id="product-search" name="search" :value="$search" placeholder="Search products or categories" maxlength="100" role="combobox" aria-autocomplete="list" aria-controls="product-search-options" aria-expanded="false" submit-on-clear>
                    <button class="product-search-dropdown" type="button" aria-label="Show products and categories" title="Show products and categories" aria-controls="product-search-options" aria-expanded="false"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"></path></svg></button>
                </x-search-field>
                <div id="product-search-options" class="product-search-options" role="listbox" hidden>
                    @foreach($categories as $category)<button type="button" class="product-search-option" role="option" data-search-value="{{ $category }}"><span>{{ $category }}</span><small>Category</small></button>@endforeach
                    @foreach($searchProducts as $productName)<button type="button" class="product-search-option" role="option" data-search-value="{{ $productName }}"><span>{{ $productName }}</span><small>Product</small></button>@endforeach
                    <p class="product-search-empty" hidden>No matching products or categories</p>
                </div>
            </div>
        </form>
        @if($canCreate)<button id="product-create-toggle" class="product-create-toggle" type="button" aria-controls="product-create-panel" aria-expanded="{{ $formContext === 'create' ? 'true' : 'false' }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg><span>{{ $formContext === 'create' ? 'Close Form' : 'Add Product' }}</span></button>@endif
        </div>
    </header>
    @if($search !== '')<p class="product-search-summary">{{ $products->count() }} {{ Str::plural('product', $products->count()) }} found for “{{ $search }}”</p>@endif
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error pm-page-error">{{ $errors->first() }}</div>@endif

    @if($canCreate)<section id="product-create-panel" class="welcome product-create-panel" {{ $formContext === 'create' ? '' : 'hidden' }}>
        <div class="pm-panel-head"><h2>New Product</h2><p class="muted">It can be sold on the POS, shop and mobile app as soon as it's saved and enabled.</p></div>
        <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" class="product-ajax-form pm-create-layout" data-action-label="Adding product...">@csrf
            <input type="hidden" name="form_context" value="create">
            <div class="pm-photo-field">
                <div class="pm-photo-preview" data-pm-preview><span class="pm-photo-empty"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><circle cx="9" cy="10" r="2"></circle><path d="m21 16-5-5-8 8"></path></svg>No picture yet</span></div>
                <input class="pm-file-input" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-pm-image-input>
                <label class="pm-upload-button" for="image">Choose Picture</label>
                <button type="button" class="pm-adjust-button" data-pm-crop-open hidden><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 2v14a2 2 0 0 0 2 2h14"></path><path d="M18 22V8a2 2 0 0 0-2-2H2"></path></svg>Adjust framing</button>
                <input type="hidden" name="image_framed" value="0" data-pm-image-framed>
                <small class="pm-hint" data-pm-file-name>JPG, PNG or WebP. You can move, zoom and rotate it before saving.</small>
            </div>
            <div class="pm-fields pm-fields-wide">
                <div class="field"><label for="name">Product Name</label><input class="control" id="name" name="name" value="{{ old('name') }}" required></div>
                <div class="field"><label for="category">Category</label>@include('products.partials.category-picker', ['id' => 'category', 'value' => old('category'), 'placeholder' => 'Select or type a new category'])</div>
                <div class="field"><label for="price">Price (₱)</label><input class="control" id="price" name="price" type="number" min="0.01" step="0.01" value="{{ old('price') }}" required></div>
                <div class="field"><label for="stock">Stock</label><input class="control" id="stock" name="stock" type="number" min="0" max="{{ $maxStock }}" step="1" inputmode="numeric" value="{{ old('stock', 0) }}" data-stock-limit required><small class="pm-hint">0–{{ $maxStock }} units</small></div>
                <div class="field pm-span"><label for="description">Description</label><textarea class="control" id="description" name="description" rows="2" maxlength="500">{{ old('description') }}</textarea></div>
                <div class="pm-span pm-form-actions">
                    <label class="pm-switch"><input name="active" type="checkbox" value="1" {{ $formContext !== 'create' || old('active') ? 'checked' : '' }}><span class="pm-switch-track" aria-hidden="true"></span><span class="pm-switch-copy"><strong>Enabled</strong><small>Disabled products can't be sold anywhere.</small></span></label>
                    <button class="button pm-primary" type="submit">Add Product</button>
                </div>
            </div>
        </form>
    </section>@endif

    @if($products->isNotEmpty())
    <section class="pm-stats" aria-label="Filter by status">
        @foreach($stats as $status => $stat)
            <button type="button" class="pm-stat pm-stat-{{ $status }}" data-pm-status="{{ $status }}" aria-pressed="{{ $status === 'all' ? 'true' : 'false' }}"><span>{{ $stat['label'] }}</span><strong>{{ $stat['count'] }}</strong></button>
        @endforeach
    </section>

    <div class="pm-toolbar">
        <div class="pm-chip-rail" data-pm-chip-rail data-at-start data-at-end>
            <button type="button" class="pm-chip-nav pm-chip-nav-prev" data-pm-chip-scroll="-1" aria-label="Scroll categories left" tabindex="-1"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"></path></svg></button>
            <div class="pm-chips" role="group" aria-label="Filter by category">
                <button type="button" class="pm-chip" data-pm-category="" aria-pressed="true">All <span>{{ $products->count() }}</span></button>
                @foreach($groups as $category => $items)<button type="button" class="pm-chip" data-pm-category="{{ $category }}" aria-pressed="false">{{ $category }} <span>{{ $items->count() }}</span></button>@endforeach
            </div>
            <button type="button" class="pm-chip-nav pm-chip-nav-next" data-pm-chip-scroll="1" aria-label="Scroll categories right" tabindex="-1"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"></path></svg></button>
        </div>
        <div class="pm-toolbar-end">
        <label class="pm-sort"><span class="pm-sr">Sort products</span><select class="control" data-pm-sort>@foreach($sortOptions as $sort => $label)<option value="{{ $sort }}">{{ $label }}</option>@endforeach</select></label>
        @if($canDelete && $categoryCounts->isNotEmpty())<button type="button" class="pm-select-toggle" data-pm-categories-open aria-haspopup="dialog" aria-controls="pm-categories"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h10"></path></svg><span>Categories</span></button>@endif
        <button type="button" class="pm-select-toggle" data-pm-select-mode aria-pressed="false" title="Select products to move to another category, enable or disable"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"></rect><path d="m8.5 12 2.5 2.5 4.5-5"></path></svg><span>Select</span></button>
        <div class="pm-view-toggle" role="group" aria-label="Layout">
            <button type="button" data-pm-view="grid" aria-pressed="true" aria-label="Grid view" title="Grid view"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="4" y="4" width="6.5" height="6.5" rx="1.5"></rect><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.5"></rect><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.5"></rect><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.5"></rect></svg></button>
            <button type="button" data-pm-view="list" aria-pressed="false" aria-label="List view" title="List view"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11"></path><circle cx="4.5" cy="6" r="1"></circle><circle cx="4.5" cy="12" r="1"></circle><circle cx="4.5" cy="18" r="1"></circle></svg></button>
        </div>
        </div>
    </div>
    @endif

    <section class="pm-catalog" data-pm-catalog data-view="grid">
        @forelse($groups as $category => $items)
        <div class="pm-group" data-pm-group data-category="{{ $category }}">
            <header class="pm-group-head"><h2>{{ $category }}</h2><span>{{ $items->count() }} {{ Str::plural('product', $items->count()) }}</span></header>
            <div class="pm-grid">
                @foreach($items as $product)
                @php
                    $stock = $stockState($product);
                    $imageUrl = $imageUrls[$product->getKey()];
                @endphp
                <article class="pm-card {{ $product->active ? '' : 'is-hidden' }}" data-pm-item
                    data-product-id="{{ $product->getKey() }}"
                    data-category="{{ $category }}"
                    data-group="{{ $loop->parent->index }}"
                    data-order="{{ $position++ }}"
                    data-active="{{ $product->active ? '1' : '0' }}"
                    data-low="{{ $product->stock <= $lowThreshold ? '1' : '0' }}"
                    data-image="{{ $imageUrl ? '1' : '0' }}"
                    data-name="{{ $product->name }}"
                    data-price="{{ $product->price }}"
                    data-stock="{{ $product->stock }}"
                    data-updated="{{ $product->updated_at?->timestamp ?? 0 }}"
                    data-description="{{ $product->description }}"
                    data-image-url="{{ $imageUrl }}"
                    data-update-url="{{ route('products.update', $product) }}"
                    data-visibility-url="{{ route('products.visibility', $product) }}"
                    @if($canDelete) data-destroy-url="{{ route('products.destroy', $product) }}" @endif>
                    <label class="pm-select"><input type="checkbox" data-pm-select value="{{ $product->getKey() }}"><span class="pm-sr">Select {{ $product->name }}</span></label>
                    <div class="pm-media">
                        @if($imageUrl)<img class="product-image" src="{{ $imageUrl }}" alt="" loading="lazy">@else<span class="pm-placeholder" aria-hidden="true">{{ $placeholder($product->name) }}</span>@endif
                        <span class="pm-flag" data-pm-flag @if($product->active) hidden @endif>Disabled</span>
                    </div>
                    <div class="pm-body">
                        <h3>{{ $product->name }}</h3>
                        <p class="pm-desc">{{ $product->description ?: 'No description' }}</p>
                    </div>
                    <div class="pm-meta">
                        <strong class="pm-price">₱{{ number_format((float) $product->price, 2) }}</strong>
                        <span class="pm-stock {{ $stock['class'] }}">{{ $stock['label'] }}</span>
                    </div>
                    <div class="pm-actions">
                        <button type="button" class="pm-toggle" role="switch" aria-checked="{{ $product->active ? 'true' : 'false' }}" data-pm-visibility title="Enable or disable this product"><span class="pm-toggle-track" aria-hidden="true"></span><span class="pm-toggle-label" data-pm-toggle-label>{{ $product->active ? 'Enabled' : 'Disabled' }}</span><span class="pm-sr"> – {{ $product->name }}</span></button>
                        <button type="button" class="pm-edit" data-pm-open aria-haspopup="dialog" aria-controls="product-editor"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4Z"></path><path d="m13.5 6.5 4 4"></path></svg><span>Edit<span class="pm-sr"> {{ $product->name }}</span></span></button>
                    </div>
                </article>
                @endforeach
            </div>
        </div>
        @empty
        <div class="welcome pm-empty-state">
            <h2>{{ $search !== '' ? 'No products match your search' : 'No products yet' }}</h2>
            <p class="muted">{{ $search !== '' ? 'Try a different name or category.' : 'Add your first product to start building the menu.' }}</p>
        </div>
        @endforelse
        @if($products->isNotEmpty())
        <div class="pm-group" data-pm-flat hidden>
            <header class="pm-group-head"><h2 data-pm-flat-title>All products</h2><span data-pm-flat-count></span></header>
            <div class="pm-grid"></div>
        </div>
        @endif
        <p class="pm-filter-empty" data-pm-empty hidden>No products match these filters.</p>
    </section>

    @if($products->isNotEmpty())
    <div class="pm-bulkbar" data-pm-bulkbar data-url="{{ route('products.visibility.bulk') }}" role="region" aria-label="Bulk actions" hidden>
        <strong data-pm-selected-count>0 selected</strong>
        <button type="button" class="pm-link" data-pm-select-all>Select all shown</button>
        <button type="button" class="pm-link" data-pm-select-none>Clear</button>
        <span class="pm-bulk-spacer"></span>
        <form method="POST" action="{{ route('products.category.bulk') }}" class="product-ajax-form pm-bulk-move" data-pm-bulk-move data-action-label="Moving...">@csrf @method('PATCH')
            <span data-pm-move-ids hidden></span>
            <label class="pm-sr" for="pm-move-category">Move selected products to</label>
            <select class="pm-bulk-select" id="pm-move-category" name="category" required data-pm-bulk-input disabled>
                <option value="" selected disabled>Move to category…</option>
                @foreach($categories as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach
            </select>
            <button type="submit" class="pm-bulk-button" data-pm-bulk-input disabled>Move</button>
        </form>
        <button type="button" class="pm-bulk-button" data-pm-bulk="1" disabled>Enable</button>
        <button type="button" class="pm-bulk-button" data-pm-bulk="0" disabled>Disable</button>
        <button type="button" class="pm-bulk-done" data-pm-select-mode>Done</button>
    </div>
    <p class="pm-sr" role="status" aria-live="polite" data-pm-live></p>

    @php
        $editing = $editingProduct !== null;
        $value = fn (string $field, $default = null) => $editing ? old($field, $default) : $default;
        $editingImage = $editing ? $imageUrls[$editingProduct->getKey()] : null;
    @endphp
    <div id="product-editor" class="pm-drawer" role="dialog" aria-modal="true" aria-labelledby="product-editor-title" data-pm-drawer data-product-id="{{ $editingProduct?->getKey() }}" {{ $editing ? '' : 'hidden' }}>
        <div class="pm-drawer-backdrop" data-pm-close></div>
        <div class="pm-drawer-panel">
            <header class="pm-drawer-head">
                <div><small>Edit product</small><h2 id="product-editor-title" data-pm-editor-title>{{ $editingProduct?->name }}</h2></div>
                <button type="button" class="pm-icon-button" data-pm-close aria-label="Close editor"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"></path></svg></button>
            </header>
            <div class="pm-drawer-scroll">
                <form id="product-edit-form" method="POST" @if($editing) action="{{ route('products.update', $editingProduct) }}" @endif enctype="multipart/form-data" class="product-ajax-form pm-drawer-form" data-action-label="Saving...">@csrf @method('PUT')
                    <input type="hidden" name="form_context" value="{{ $editing ? $formContext : '' }}">
                    @if($editing && $errors->any())<div class="product-ajax-inline-error" role="alert">{{ $errors->first() }}</div>@endif
                    <div class="pm-photo-field pm-photo-row">
                        <div class="pm-photo-preview" data-pm-preview>@if($editingImage)<img class="product-image" src="{{ $editingImage }}" alt="">@elseif($editing)<span class="pm-placeholder" aria-hidden="true">{{ $placeholder($editingProduct->name) }}</span>@endif</div>
                        <div class="pm-photo-actions">
                            <input class="pm-file-input" id="image-edit" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-pm-image-input>
                            <label class="pm-upload-button" for="image-edit" data-pm-upload-label>{{ $editingImage ? 'Replace Picture' : 'Add Picture' }}</label>
                            <button type="button" class="pm-adjust-button" data-pm-crop-open @if($editingImage) data-source-url="{{ $editingImage }}" @else hidden @endif><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 2v14a2 2 0 0 0 2 2h14"></path><path d="M18 22V8a2 2 0 0 0-2-2H2"></path></svg>Adjust framing</button>
                            <input type="hidden" name="image_framed" value="0" data-pm-image-framed>
                            <small class="pm-hint" data-pm-file-name>JPG, PNG or WebP. You can move, zoom and rotate it before saving.</small>
                            <label class="pm-inline-check" data-pm-remove-image @unless($editingImage) hidden @endunless><input name="remove_image" type="checkbox" value="1"> Remove current picture</label>
                        </div>
                    </div>
                    <div class="pm-fields">
                        <div class="field pm-span"><label for="name-edit">Name</label><input class="control" id="name-edit" name="name" value="{{ $value('name', $editingProduct?->name) }}" required></div>
                        <div class="field pm-span"><label for="category-edit">Category</label>@include('products.partials.category-picker', ['id' => 'category-edit', 'value' => $value('category', $editingProduct?->category), 'placeholder' => 'Select or type a category'])</div>
                        <div class="field"><label for="price-edit">Price (₱)</label><input class="control" id="price-edit" name="price" type="number" min="0.01" step="0.01" value="{{ $value('price', $editingProduct?->price) }}" required></div>
                        <div class="field"><span class="pm-field-label">Stock</span><div class="pm-stock-readonly"><strong data-pm-editor-stock>{{ $editingProduct?->stock ?? 0 }} units</strong><a href="{{ route('inventory.index') }}">Adjust in Inventory</a></div></div>
                        <div class="field pm-span"><label for="description-edit">Description</label><textarea class="control" id="description-edit" name="description" rows="3" maxlength="500">{{ $value('description', $editingProduct?->description) }}</textarea></div>
                    </div>
                    <label class="pm-switch"><input name="active" type="checkbox" value="1" {{ $editing && old('active') ? 'checked' : '' }}><span class="pm-switch-track" aria-hidden="true"></span><span class="pm-switch-copy"><strong>Enabled</strong><small>Disabled products can't be sold on the POS, shop, mobile app or reservations. Their sales history is kept.</small></span></label>
                </form>
                @if($canDelete)
                <form method="POST" @if($editing) action="{{ route('products.destroy', $editingProduct) }}" @endif class="product-ajax-form pm-danger" data-pm-delete-form data-action-label="Deleting..." data-confirm="Permanently delete this product?" data-confirm-title="Delete product?">@csrf @method('DELETE')
                    <div><strong>Delete product</strong><small>Only products with no order, reservation or inventory history can be deleted.</small></div>
                    <button class="pm-delete" type="submit">Delete</button>
                </form>
                @endif
            </div>
            <footer class="pm-drawer-foot">
                <button type="button" class="logout" data-pm-close>Cancel</button>
                <button class="button pm-primary" type="submit" form="product-edit-form">Save Changes</button>
            </footer>
        </div>
    </div>
    @endif

    @if($canDelete && $categoryCounts->isNotEmpty())
    <div id="pm-categories" class="pm-cropper pm-categories" data-pm-categories role="dialog" aria-modal="true" aria-labelledby="pm-categories-title" hidden>
        <div class="pm-cropper-backdrop" data-pm-categories-close></div>
        <div class="pm-cropper-panel">
            <header class="pm-cropper-head">
                <div><h2 id="pm-categories-title">Categories</h2><p>Deleting a category moves its products to another category first, so no product or sales record is lost.</p></div>
                <button type="button" class="pm-icon-button" data-pm-categories-close aria-label="Close"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"></path></svg></button>
            </header>
            <ul class="pm-category-list">
                @foreach($categoryCounts as $category => $total)
                <li class="pm-category-row">
                    <div class="pm-category-summary">
                        <div><strong>{{ $category }}</strong><small>{{ $total }} {{ Str::plural('product', $total) }}</small></div>
                        <button type="button" class="pm-category-delete-toggle" data-pm-category-delete aria-expanded="false">Delete</button>
                    </div>
                    <form method="POST" action="{{ route('products.categories.destroy') }}" class="product-ajax-form pm-category-delete" data-action-label="Deleting..." data-confirm="Delete the {{ $category }} category? Its {{ $total }} {{ Str::plural('product', $total) }} will move to the category you chose." data-confirm-title="Delete category?" hidden>@csrf @method('DELETE')
                        <input type="hidden" name="category" value="{{ $category }}">
                        <label for="move-{{ $loop->index }}">Move its {{ $total }} {{ Str::plural('product', $total) }} to</label>
                        <select class="control" id="move-{{ $loop->index }}" name="move_to" required>
                            @foreach($categoryCounts->keys()->reject(fn ($other) => $other === $category) as $other)<option value="{{ $other }}">{{ $other }}</option>@endforeach
                            @unless($category === 'Uncategorized' || $categoryCounts->has('Uncategorized'))<option value="Uncategorized">Uncategorized</option>@endunless
                        </select>
                        <div class="pm-category-actions"><button type="button" class="logout" data-pm-category-cancel>Cancel</button><button type="submit" class="pm-delete">Delete category</button></div>
                    </form>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <div class="pm-cropper" data-pm-cropper role="dialog" aria-modal="true" aria-labelledby="pm-cropper-title" aria-describedby="pm-cropper-help" hidden>
        <div class="pm-cropper-backdrop" data-pm-crop-cancel></div>
        <div class="pm-cropper-panel">
            <header class="pm-cropper-head">
                <div><h2 id="pm-cropper-title">Adjust picture</h2><p id="pm-cropper-help">Drag to move. Scroll, pinch or use the slider to zoom. Keep the dish inside the dashed guide so it matches the other pictures.</p></div>
                <button type="button" class="pm-icon-button" data-pm-crop-cancel aria-label="Cancel"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"></path></svg></button>
            </header>
            <div class="pm-crop-stage" data-pm-crop-stage tabindex="0" role="img" aria-label="Picture framing. Use the arrow keys to move and the plus and minus keys to zoom.">
                <canvas data-pm-crop-canvas></canvas>
                <span class="pm-crop-guide" aria-hidden="true"></span>
                <span class="pm-crop-status" data-pm-crop-status>Loading picture…</span>
            </div>
            <div class="pm-crop-controls">
                <button type="button" class="pm-icon-button" data-pm-crop-rotate="-1" aria-label="Rotate left" title="Rotate left"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path><path d="M3 3v5h5"></path></svg></button>
                <label class="pm-crop-zoom"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="M8 11h6M20 20l-4-4"></path></svg><span class="pm-sr">Zoom</span><input type="range" min="0" max="1" step="0.001" value="0.5" data-pm-crop-zoom><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="M8 11h6M11 8v6M20 20l-4-4"></path></svg></label>
                <button type="button" class="pm-icon-button" data-pm-crop-rotate="1" aria-label="Rotate right" title="Rotate right"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"></path><path d="M21 3v5h-5"></path></svg></button>
            </div>
            <div class="pm-crop-presets" role="group" aria-label="Quick framing">
                <button type="button" data-pm-crop-preset="standard">Standard</button>
                <button type="button" data-pm-crop-preset="fit">Whole picture</button>
                <button type="button" data-pm-crop-preset="fill">Fill frame</button>
            </div>
            <footer class="pm-cropper-foot">
                <button type="button" class="logout" data-pm-crop-cancel>Cancel</button>
                <button type="button" class="button pm-primary" data-pm-crop-apply disabled>Apply</button>
            </footer>
        </div>
    </div>

    <template id="category-options-template">
        @foreach($categories as $category)<button type="button" class="product-search-option" role="option" data-category="{{ $category }}"><span>{{ $category }}</span></button>@endforeach
        <p class="product-search-empty" hidden>New category — it will be added when you save.</p>
    </template>
</div></main></div>
@push('styles')
<style>
.product-management-header{gap:24px}
.product-page-heading{flex:0 0 auto}
.product-page-heading h1{margin:0;font-size:28px;font-weight:700;line-height:1.2;letter-spacing:-.035em}
.product-page-heading .muted{margin:4px 0 0;font-size:14px}
.product-header-actions{min-width:0;flex:1;display:flex;align-items:center;justify-content:flex-end;gap:10px}
.product-search-form{width:min(560px,100%);min-width:0;display:flex;align-items:center;justify-content:flex-end;gap:8px}
.product-search-control{position:relative;min-width:0;flex:1}
.product-search-dropdown{width:32px;height:32px;flex:0 0 32px;padding:0;display:grid;place-items:center;border:0;border-radius:8px;background:transparent;color:#62675f;cursor:pointer}
.product-search-dropdown:hover{background:#eff0ea;color:#171817}
.product-search-dropdown svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.product-search-dropdown[aria-expanded="true"] svg{transform:rotate(180deg)}
.product-search-options{position:absolute;z-index:30;top:calc(100% + 7px);left:0;right:0;max-height:310px;overflow-y:auto;padding:7px;border:1px solid #dfe1da;border-radius:8px;background:#fff;box-shadow:0 12px 28px rgba(23,24,23,.14)}
.product-search-option{width:100%;min-height:42px;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:9px 11px;border:0;border-radius:6px;background:transparent;color:#252724;font-family:inherit;font-size:14px;text-align:left;cursor:pointer}
.product-search-option[hidden],.product-search-empty[hidden]{display:none}
.product-search-option:hover,.product-search-option.is-active{background:#eff0ea;color:#171817}
.product-search-option small{color:#81867e;font-size:11px;white-space:nowrap}
.product-search-empty{margin:0;padding:14px 11px;color:#777d74;font-size:13px;text-align:center}
.product-search-summary{margin:-12px 0 20px;color:#6d736a;font-size:13px;text-align:right}
.product-create-toggle{height:44px;flex:0 0 auto;display:flex;align-items:center;gap:8px;padding:0 18px;border:0;border-radius:10px;background:#171817;color:#fff;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;white-space:nowrap}
.product-create-toggle:hover{background:#30322e}
.product-create-toggle svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;transition:transform .18s}
.product-create-toggle[aria-expanded="true"] svg{transform:rotate(45deg)}
.pm-page-error{background:#fff0f0;padding:12px;border-radius:9px;margin-bottom:18px}
.pm-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}

/* Create panel */
.product-create-panel{margin:0 0 22px;padding:26px}
.pm-panel-head{margin-bottom:20px}
.pm-panel-head h2{margin:0;font-size:20px}
.pm-panel-head .muted{margin:4px 0 0;font-size:13px}
.pm-create-layout{display:grid;grid-template-columns:210px minmax(0,1fr);gap:26px;align-items:start}
.pm-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.pm-fields .field{margin:0;min-width:0}
.pm-fields label:not(.pm-switch){display:block;margin-bottom:6px;font-size:13px;font-weight:700;color:#3b3f38}
.pm-fields-wide{grid-template-columns:minmax(0,2fr) minmax(0,1.4fr) minmax(0,1fr) minmax(0,1fr)}
.pm-span{grid-column:1/-1}
.pm-field-label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;color:#3b3f38}
.pm-stock-readonly{min-height:44px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:8px 12px;border:1px dashed #cfd2c8;border-radius:11px;background:#f7f7f2}
.pm-stock-readonly strong{font-size:15px;font-variant-numeric:tabular-nums}
.pm-hint{display:block;margin-top:5px;color:#80857c;font-size:12px}
.pm-form-actions{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding-top:4px}
.pm-primary{width:auto;padding-inline:26px}

/* Picture field */
.pm-photo-field{display:grid;gap:8px;align-content:start}
.pm-photo-preview{position:relative;aspect-ratio:1;border:1px dashed #c9ccbf;border-radius:14px;overflow:hidden;background:#fff;display:grid;place-items:center}
.pm-photo-preview img{width:100%;height:100%;object-fit:cover}
.pm-photo-empty{display:grid;justify-items:center;gap:6px;color:#80857c;font-size:12px}
.pm-photo-empty svg{width:30px;height:30px;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.pm-file-input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.pm-upload-button{height:38px;display:inline-flex;align-items:center;justify-content:center;padding:0 14px;border:1px solid #d2d5cb;border-radius:10px;background:#fff;color:#252724;font-size:13px;font-weight:700;cursor:pointer}
.pm-upload-button:hover{background:#eff0ea}
.pm-file-input:focus-visible+.pm-upload-button{outline:2px solid #98a20f;outline-offset:2px}
.pm-photo-row{grid-template-columns:128px minmax(0,1fr);gap:16px;align-items:center}
.pm-photo-row .pm-photo-preview{aspect-ratio:1}
.pm-photo-actions{display:grid;gap:8px;justify-items:start}
.pm-photo-actions .pm-hint{margin:0}
.pm-inline-check{display:flex;align-items:center;gap:8px;color:#536078;font-size:13px;cursor:pointer}
.pm-inline-check input{width:16px;height:16px}

/* Visibility switch */
.pm-switch{display:flex;align-items:center;gap:12px;cursor:pointer}
.pm-switch input{position:absolute;opacity:0;width:1px;height:1px}
.pm-switch-track{position:relative;display:block;flex:0 0 42px;height:24px;border-radius:999px;background:#cfd2c8;transition:background-color .15s}
.pm-switch-track::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:transform .15s}
.pm-switch input:checked+.pm-switch-track{background:#7f8a0c}
.pm-switch input:checked+.pm-switch-track::after{transform:translateX(18px)}
.pm-switch input:focus-visible+.pm-switch-track{outline:2px solid #98a20f;outline-offset:2px}
.pm-switch-copy{display:grid;gap:2px}
.pm-switch-copy strong{font-size:14px}
.pm-switch-copy small{color:#80857c;font-size:12px}

/* Status tiles double as filters */
.pm-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:18px}
.pm-stat{display:grid;gap:4px;padding:16px 18px;border:1px solid var(--line,#d8d9cf);border-radius:14px;background:var(--surface,#fffefa);color:inherit;font:inherit;text-align:left;cursor:pointer}
.pm-stat span{color:#6c7068;font-size:13px;font-weight:600}
.pm-stat strong{font-size:26px;line-height:1.1;letter-spacing:-.03em}
.pm-stat:hover{border-color:#b9beb0}
.pm-stat[aria-pressed="true"]{border-color:#171817;box-shadow:inset 0 0 0 1px #171817}
.pm-stat-disabled strong{color:#6c7068}
.pm-stat-low strong{color:#b42318}

/* Toolbar */
.pm-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:6px}
.pm-chip-rail{position:relative;flex:1;min-width:0;display:flex;align-items:center}
.pm-chips{--fade-start:0px;--fade-end:0px;position:relative;flex:1;min-width:0;display:flex;flex-wrap:nowrap;gap:8px;padding:2px 0;overflow-x:auto;overscroll-behavior-x:contain;scroll-behavior:smooth;scroll-snap-type:x proximity;scroll-padding-inline:44px;scrollbar-width:none;-webkit-mask-image:linear-gradient(90deg,transparent,#000 var(--fade-start),#000 calc(100% - var(--fade-end)),transparent);mask-image:linear-gradient(90deg,transparent,#000 var(--fade-start),#000 calc(100% - var(--fade-end)),transparent);cursor:grab;user-select:none}
.pm-chips::-webkit-scrollbar{display:none}
.pm-chips.is-dragging{cursor:grabbing;scroll-behavior:auto;scroll-snap-type:none}
.pm-chips.is-dragging .pm-chip{pointer-events:none}
.pm-chip-rail:not([data-at-start]) .pm-chips{--fade-start:48px}
.pm-chip-rail:not([data-at-end]) .pm-chips{--fade-end:48px}
.pm-chip-nav{position:absolute;z-index:2;top:50%;width:32px;height:32px;display:grid;place-items:center;padding:0;border:1px solid #d8d9cf;border-radius:50%;background:#fff;color:#171817;box-shadow:0 2px 8px rgba(23,24,23,.12);cursor:pointer;transform:translateY(-50%);transition:opacity .15s}
.pm-chip-nav:hover{background:#eff0ea}
.pm-chip-nav svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.pm-chip-nav-prev{left:0}
.pm-chip-nav-next{right:0}
.pm-chip-rail[data-at-start] .pm-chip-nav-prev,.pm-chip-rail[data-at-end] .pm-chip-nav-next{opacity:0;pointer-events:none}
.pm-chip{flex:0 0 auto;white-space:nowrap;scroll-snap-align:start;display:inline-flex;align-items:center;gap:7px;padding:7px 13px;border:1px solid #d8d9cf;border-radius:999px;background:#fff;color:inherit;font:inherit;font-size:13px;cursor:pointer}
.pm-chip span{min-width:20px;padding:1px 6px;border-radius:999px;background:#f0f1ea;color:#6c7068;font-size:11px;font-weight:700;text-align:center}
.pm-chip:hover{border-color:#8d960f}
.pm-chip[aria-pressed="true"]{background:#171817;border-color:#171817;color:#fff}
.pm-chip[aria-pressed="true"] span{background:rgba(255,255,255,.18);color:#fff}
.pm-stat-noimage strong{color:#8a5200}
.pm-toolbar-end{flex:0 0 auto;display:flex;align-items:center;gap:8px}
.pm-sort select{height:40px;padding:0 34px 0 12px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer}
.pm-select-toggle{height:40px;display:inline-flex;align-items:center;gap:7px;padding:0 13px;border:1px solid #d8d9cf;border-radius:10px;background:#fff;color:#171817;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
.pm-select-toggle svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.pm-select-toggle:hover{background:#eff0ea}
.pm-select-toggle[aria-pressed="true"]{background:#171817;border-color:#171817;color:#fff}
.pm-view-toggle{flex:0 0 auto;display:flex;padding:3px;border:1px solid #d8d9cf;border-radius:10px;background:#fff}
.pm-view-toggle button{width:34px;height:32px;display:grid;place-items:center;border:0;border-radius:7px;background:transparent;color:#6c7068;cursor:pointer}
.pm-view-toggle button[aria-pressed="true"]{background:#171817;color:#fff}
.pm-view-toggle svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round}

/* Catalog */
.pm-group{margin-top:22px}
.pm-group[hidden]{display:none}
.pm-group-head{display:flex;align-items:baseline;gap:12px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #dfe1d7}
.pm-group-head h2{margin:0;font-size:18px;letter-spacing:-.02em}
.pm-group-head span{color:#6c7068;font-size:13px}
.pm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,220px),1fr));gap:16px}
.pm-card{position:relative;display:flex;flex-direction:column;min-width:0;border:1px solid #d8d9cf;border-radius:16px;background:var(--surface,#fffefa);overflow:hidden;transition:border-color .15s,box-shadow .15s}
.pm-card[hidden]{display:none}
.pm-card:hover{border-color:#aeb39f;box-shadow:0 10px 26px rgba(24,25,22,.08)}
.pm-card:focus-within{border-color:#8d960f}
.pm-media{position:relative;aspect-ratio:1;background:#fff;border-bottom:1px solid #eceee6}
.pm-media img{display:block;width:100%;height:100%;object-fit:cover}
.pm-placeholder{width:100%;height:100%;display:grid;place-items:center;background:#eff1df;color:#747d00;font-size:34px;font-weight:800}
.pm-card.is-hidden .pm-media img,.pm-card.is-hidden .pm-placeholder{opacity:.55;filter:grayscale(.7)}
.pm-flag{position:absolute;top:10px;left:10px;padding:4px 10px;border-radius:999px;background:rgba(23,24,23,.84);color:#fff;font-size:11px;font-weight:700;letter-spacing:.02em}
.pm-body{flex:1;padding:14px 16px 0;min-width:0}
.pm-body h3{margin:0;font-size:15px;line-height:1.3;overflow-wrap:anywhere}
.pm-desc{margin:5px 0 0;color:#6c7068;font-size:13px;line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pm-meta{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:12px 16px}
.pm-price{font-size:17px;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.pm-stock{padding:4px 9px;border-radius:999px;font-size:12px;font-weight:700;white-space:nowrap}
.pm-stock.is-ok{background:#e7f4eb;color:#23683c}
.pm-stock.is-low{background:#fff1d6;color:#8a5200}
.pm-stock.is-out{background:#fde9e7;color:#b42318}
.pm-actions{display:flex;align-items:center;gap:8px;padding:0 16px 16px}
.pm-toggle{position:relative;z-index:2;height:40px;flex:0 0 auto;display:inline-flex;align-items:center;gap:8px;padding:0 11px 0 9px;border:1px solid #d8d9cf;border-radius:10px;background:#fff;color:#3b3f38;font:inherit;font-size:13px;font-weight:700;white-space:nowrap;cursor:pointer}
.pm-toggle:hover{border-color:#aeb39f}
.pm-toggle-track{position:relative;flex:0 0 32px;height:18px;border-radius:999px;background:#cfd2c8;transition:background-color .15s}
.pm-toggle-track::after{content:"";position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .15s}
.pm-toggle[aria-checked="true"] .pm-toggle-track{background:#7f8a0c}
.pm-toggle[aria-checked="true"] .pm-toggle-track::after{transform:translateX(14px)}
.pm-toggle[aria-busy="true"]{opacity:.6;pointer-events:none}
.pm-toggle:focus-visible,.pm-edit:focus-visible{outline:2px solid #98a20f;outline-offset:2px}
.pm-edit{height:40px;flex:1;min-width:0;display:flex;align-items:center;justify-content:center;gap:8px;border:1px solid #d8d9cf;border-radius:10px;background:#fff;color:#171817;font:inherit;font-size:14px;font-weight:700;cursor:pointer}
.pm-edit::after{content:"";position:absolute;inset:0}
.pm-card:hover .pm-edit{background:#edf0cf;border-color:#c6cc8d}
.pm-edit svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linejoin:round}

/* Select mode: the whole card becomes the checkbox target */
.pm-select{display:none}
.pm-catalog[data-selecting] .pm-select{display:contents}
.pm-select input{position:absolute;z-index:4;top:10px;right:10px;width:22px;height:22px;margin:0;accent-color:#171817;cursor:pointer}
.pm-select::after{content:"";position:absolute;z-index:3;inset:0;cursor:pointer}
.pm-catalog[data-selecting] .pm-card:hover{border-color:#8d960f}
.pm-card.is-selected{border-color:#171817;box-shadow:inset 0 0 0 1px #171817}
.pm-catalog[data-selecting] .pm-actions{opacity:.45}
.pm-bulkbar{position:sticky;z-index:20;bottom:16px;display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-top:22px;padding:12px 14px 12px 18px;border-radius:14px;background:#171817;color:#fff;box-shadow:0 18px 40px rgba(10,12,9,.28)}
.pm-bulkbar[hidden]{display:none}
.pm-bulkbar strong{font-size:14px;white-space:nowrap}
.pm-bulk-spacer{flex:1}
.pm-link{padding:6px 4px;border:0;background:none;color:#d9dcc7;font:inherit;font-size:13px;font-weight:600;text-decoration:underline;text-underline-offset:3px;cursor:pointer}
.pm-link:hover{color:#fff}
.pm-bulk-button,.pm-bulk-done{height:38px;padding:0 14px;border:1px solid #454840;border-radius:10px;background:#292b27;color:#fff;font:inherit;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap}
.pm-bulk-button:hover:not(:disabled){background:#3a3d36}
.pm-bulk-button[data-pm-bulk="1"]:not(:disabled){background:#aebb19;border-color:#aebb19;color:#171817}
.pm-bulk-button:disabled{opacity:.45;cursor:not-allowed}
.pm-bulk-done{background:transparent}
.pm-bulk-move{display:flex;align-items:center;gap:8px;padding-right:10px;margin-right:2px;border-right:1px solid #454840}
.pm-bulk-select{height:38px;max-width:220px;padding:0 30px 0 12px;border:1px solid #454840;border-radius:10px;background:#292b27 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23d9dcc7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m7 10 5 5 5-5'/%3E%3C/svg%3E") no-repeat right 9px center/16px;color:#fff;font:inherit;font-size:13px;font-weight:600;cursor:pointer;appearance:none}
.pm-bulk-select option{background:#fff;color:#171817}
.pm-bulk-select:disabled{opacity:.45;cursor:not-allowed}
.pm-bulk-select:focus-visible{outline:2px solid #aebb19;outline-offset:2px}
.pm-bulk-move .product-ajax-inline-error{display:none}
.pm-filter-empty{margin:24px 0 0;color:#6c7068;text-align:center}
.pm-empty-state{text-align:center}
.pm-empty-state h2{margin:0 0 6px;font-size:20px}
.pm-empty-state p{margin:0}

/* List view */
.pm-catalog[data-view="list"] .pm-grid{grid-template-columns:minmax(0,1fr);gap:8px}
.pm-catalog[data-view="list"] .pm-card{display:grid;grid-template-columns:56px minmax(0,1fr) auto auto;align-items:center;gap:16px;padding:10px 14px;border-radius:12px}
.pm-catalog[data-view="list"] .pm-media{width:56px;height:56px;aspect-ratio:auto;border-radius:10px;overflow:hidden}
.pm-catalog[data-view="list"] .pm-placeholder{font-size:20px}
.pm-catalog[data-view="list"] .pm-flag{top:auto;bottom:3px;left:3px;right:3px;padding:1px 0;font-size:9px;text-align:center}
.pm-catalog[data-view="list"] .pm-body{padding:0}
.pm-catalog[data-view="list"] .pm-desc{-webkit-line-clamp:1;line-clamp:1}
.pm-catalog[data-view="list"] .pm-meta{padding:0;flex-wrap:nowrap;gap:14px}
.pm-catalog[data-view="list"] .pm-price{min-width:96px;text-align:right}
.pm-catalog[data-view="list"] .pm-stock{min-width:112px;text-align:center}
.pm-catalog[data-view="list"] .pm-actions{padding:0}
.pm-catalog[data-view="list"] .pm-edit{flex:0 0 auto;padding:0 14px}
.pm-catalog[data-view="list"] .pm-toggle{min-width:118px}
.pm-catalog[data-view="list"] .pm-select input{top:50%;right:auto;left:10px;transform:translateY(-50%)}
.pm-catalog[data-view="list"][data-selecting] .pm-card{padding-left:34px}

/* Edit drawer (sits below the z-index 4000 confirm dialog) */
body.pm-drawer-open{overflow:hidden}
body.pm-drawer-open .admin-workspace{overflow:hidden!important}
.pm-drawer{position:fixed;inset:0;z-index:3000;display:flex;justify-content:flex-end}
.pm-drawer[hidden]{display:none}
.pm-drawer-backdrop{position:absolute;inset:0;background:rgba(16,18,15,.48);backdrop-filter:blur(3px);animation:pm-fade .2s ease}
.pm-drawer-panel{position:relative;width:min(520px,100%);height:100%;display:flex;flex-direction:column;background:#fffefa;box-shadow:-24px 0 60px rgba(10,12,9,.2);animation:pm-slide .24s cubic-bezier(.22,.72,.18,1)}
.pm-drawer-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px 18px;border-bottom:1px solid #e3e4dc}
.pm-drawer-head small{color:#6c7068;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
.pm-drawer-head h2{margin:4px 0 0;font-size:21px;letter-spacing:-.02em;overflow-wrap:anywhere}
.pm-icon-button{flex:0 0 auto;width:38px;height:38px;display:grid;place-items:center;padding:0;border:0;border-radius:10px;background:transparent;color:#555b52;cursor:pointer}
.pm-icon-button:hover{background:#eff0ea;color:#171817}
.pm-icon-button svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}
.pm-drawer-scroll{flex:1;overflow-y:auto;padding:22px 24px;display:grid;align-content:start;gap:22px}
.pm-drawer-form{display:grid;gap:20px}
.pm-drawer-foot{display:flex;justify-content:flex-end;gap:10px;padding:14px 24px;border-top:1px solid #e3e4dc;background:#fffefa}
.pm-drawer-foot .logout,.pm-drawer-foot .button{min-height:44px;width:auto}
.pm-danger{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;border:1px solid #efc8c5;border-radius:12px;background:#fff8f7}
.pm-danger div{display:grid;gap:3px}
.pm-danger strong{color:#b42318;font-size:14px}
.pm-danger small{color:#80655f;font-size:12px}
.pm-delete{flex:0 0 auto;height:38px;padding:0 16px;border:1px solid #e3b4b0;border-radius:10px;background:#fff;color:#b42318;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
.pm-delete:hover{background:#b42318;border-color:#b42318;color:#fff}
.pm-adjust-button{height:34px;display:inline-flex;align-items:center;gap:7px;padding:0 12px;border:1px solid #c6cc8d;border-radius:9px;background:#edf0cf;color:#3f4500;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
.pm-adjust-button[hidden]{display:none}
.pm-adjust-button:hover{background:#e2e7b5}
.pm-adjust-button svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* Picture cropper (above the edit drawer, below the z-index 4000 alerts) */
body.pm-cropper-open{overflow:hidden}
body.pm-cropper-open .admin-workspace{overflow:hidden!important}
.pm-cropper{position:fixed;inset:0;z-index:3500;display:grid;place-items:center;padding:16px}
.pm-cropper[hidden]{display:none}
.pm-cropper-backdrop{position:absolute;inset:0;background:rgba(16,18,15,.6);backdrop-filter:blur(3px);animation:pm-fade .18s ease}
.pm-cropper-panel{position:relative;width:min(470px,100%);max-height:calc(100dvh - 32px);overflow-y:auto;display:grid;gap:14px;padding:20px;border-radius:18px;background:#fffefa;box-shadow:0 28px 80px rgba(10,12,9,.35);animation:pm-fade .18s ease}
.pm-cropper-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.pm-cropper-head h2{margin:0;font-size:19px;letter-spacing:-.02em}
.pm-cropper-head p{margin:4px 0 0;color:#6c7068;font-size:13px;line-height:1.45}
.pm-crop-stage{position:relative;aspect-ratio:1;border-radius:12px;overflow:hidden;background:#fff;box-shadow:inset 0 0 0 1px #d8d9cf;cursor:grab;touch-action:none;user-select:none}
.pm-crop-stage:active{cursor:grabbing}
.pm-crop-stage:focus-visible{outline:2px solid #98a20f;outline-offset:3px}
.pm-crop-stage canvas{display:block;width:100%;height:100%}
.pm-crop-guide{position:absolute;inset:10%;border:1.5px dashed rgba(23,24,23,.38);border-radius:8px;pointer-events:none}
.pm-crop-status{position:absolute;inset:0;display:grid;place-items:center;padding:20px;background:rgba(255,255,255,.9);color:#6c7068;font-size:14px;text-align:center}
.pm-crop-status[hidden]{display:none}
.pm-crop-controls{display:flex;align-items:center;gap:8px}
.pm-crop-zoom{flex:1;display:flex;align-items:center;gap:8px;color:#6c7068}
.pm-crop-zoom svg{width:18px;height:18px;flex:0 0 18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}
.pm-crop-zoom input{flex:1;min-width:0;padding:0;border:0;box-shadow:none;accent-color:#171817}
.pm-crop-presets{display:flex;gap:8px}
.pm-crop-presets button{flex:1;height:36px;border:1px solid #d8d9cf;border-radius:9px;background:#fff;color:#171817;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
.pm-crop-presets button:hover{background:#eff0ea}
.pm-cropper-foot{display:flex;justify-content:flex-end;gap:10px}
.pm-cropper-foot .logout,.pm-cropper-foot .button{min-height:44px;width:auto}
.pm-cropper-foot .button:disabled{opacity:.5}
@media(max-width:520px){.pm-cropper{padding:0;place-items:end stretch}.pm-cropper-panel{width:100%;max-height:100dvh;border-radius:18px 18px 0 0;padding:16px}.pm-cropper-foot .logout,.pm-cropper-foot .button{flex:1}}
/* Category management dialog (reuses the cropper's dialog frame) */
.pm-categories .pm-cropper-panel{width:min(520px,100%)}
.pm-category-list{margin:0;padding:0;list-style:none;display:grid;gap:8px}
.pm-category-row{padding:12px 14px;border:1px solid #dfe1d7;border-radius:12px;background:#fff}
.pm-category-summary{display:flex;align-items:center;justify-content:space-between;gap:12px}
.pm-category-summary strong{display:block;font-size:15px}
.pm-category-summary small{color:#6c7068;font-size:12px}
.pm-category-delete-toggle{height:34px;padding:0 12px;border:1px solid #e3b4b0;border-radius:9px;background:#fff;color:#b42318;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
.pm-category-delete-toggle:hover,.pm-category-delete-toggle[aria-expanded="true"]{background:#fff4f2}
.pm-category-delete{display:grid;gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid #f0e0de}
.pm-category-delete[hidden]{display:none}
.pm-category-delete label{font-size:13px;font-weight:700;color:#3b3f38}
.pm-category-actions{display:flex;justify-content:flex-end;gap:8px}
.pm-category-actions .logout{min-height:38px;width:auto}
@keyframes pm-slide{from{transform:translateX(40px);opacity:0}to{transform:none;opacity:1}}
@keyframes pm-fade{from{opacity:0}to{opacity:1}}

.category-picker{position:relative}
.category-picker-input{padding-right:42px}
.category-picker-toggle{position:absolute;z-index:2;top:50%;right:5px;width:32px;height:32px;padding:0;display:grid;place-items:center;border:0;border-radius:6px;background:transparent;color:#62675f;cursor:pointer;transform:translateY(-50%)}
.category-picker-toggle:hover{background:#eff0ea;color:#171817}
.category-picker-toggle svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.category-picker-toggle[aria-expanded="true"] svg{transform:rotate(180deg)}
.category-picker .product-search-options{max-height:260px}
.product-ajax-form[aria-busy="true"]{opacity:.72;pointer-events:none}
.product-ajax-inline-error{margin:0 0 14px;padding:11px 13px;border:1px solid #efc8c5;border-radius:9px;background:#fff0f0;color:#a51d16;font-size:13px}
@media(max-width:1100px){.product-management-header{display:grid;grid-template-columns:minmax(0,1fr);gap:18px;align-items:start}.product-header-actions,.product-search-form{width:100%;justify-content:stretch}.product-search-summary{margin-top:-10px;text-align:left}.pm-fields-wide{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.pm-create-layout{grid-template-columns:minmax(0,1fr)}.pm-create-layout>.pm-photo-field{grid-template-columns:120px minmax(0,1fr);align-items:center}.pm-create-layout>.pm-photo-field .pm-photo-preview{grid-row:span 3;aspect-ratio:1}.pm-catalog[data-view="list"] .pm-desc{display:none}}
@media(max-width:1180px){.pm-toolbar{flex-direction:column;align-items:stretch;gap:12px}.pm-toolbar-end{justify-content:flex-end}}
@media(max-width:780px){.pm-stats{display:flex;overflow-x:auto;scrollbar-width:none;gap:10px}.pm-stats::-webkit-scrollbar{display:none}.pm-stat{flex:0 0 132px}.pm-toolbar-end{justify-content:stretch}.pm-sort{flex:1;min-width:0}.pm-sort select{width:100%}.pm-bulkbar{bottom:10px;padding:12px}.pm-bulk-spacer{display:none}.pm-bulk-button{flex:1}.pm-catalog[data-view="list"] .pm-actions{grid-column:3;grid-row:1/span 2;flex-direction:column;align-items:stretch;gap:6px}.pm-catalog[data-view="list"] .pm-toggle{min-width:0;height:34px}.pm-catalog[data-view="list"] .pm-edit{height:34px}.pm-chip-nav{display:none}.pm-chips{scroll-padding-inline:0}.pm-catalog[data-view="list"] .pm-card{grid-template-columns:48px minmax(0,1fr) auto;gap:12px}.pm-catalog[data-view="list"] .pm-media{width:48px;height:48px}.pm-catalog[data-view="list"] .pm-meta{grid-column:2;grid-row:2;justify-content:flex-start}.pm-catalog[data-view="list"] .pm-price,.pm-catalog[data-view="list"] .pm-stock{min-width:0;text-align:left}.pm-drawer-foot .logout,.pm-drawer-foot .button{flex:1}}
@media(max-width:640px){.product-header-actions{display:grid;gap:10px}.product-search-form{width:100%}.product-create-toggle{width:100%;justify-content:center}.pm-drawer-panel{width:100%}.pm-drawer-head,.pm-drawer-scroll,.pm-drawer-foot{padding-inline:16px}}
@media(max-width:520px){.pm-fields,.pm-fields-wide{grid-template-columns:minmax(0,1fr)}.pm-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.pm-body{padding:10px 12px 0}.pm-body h3{font-size:14px}.pm-desc{display:none}.pm-meta{padding:8px 12px 10px;gap:6px}.pm-price{font-size:15px}.pm-stock{font-size:11px;padding:3px 7px}.pm-actions{padding:0 12px 12px;gap:6px}.pm-edit{height:36px;font-size:13px}.pm-edit svg{display:none}.pm-toggle{height:36px;padding:0 8px}.pm-catalog[data-view="grid"] .pm-toggle-label{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}.pm-stat{padding:12px 14px}.pm-stat strong{font-size:22px}.pm-photo-row{grid-template-columns:96px minmax(0,1fr)}.pm-danger{flex-direction:column;align-items:stretch}.pm-primary{width:100%}}
@media(max-width:780px){.pm-bulk-move{flex:1 1 100%;padding-right:0;margin-right:0;border-right:0}.pm-bulk-select{flex:1;min-width:0;max-width:none}.pm-bulk-move .pm-bulk-button{flex:0 0 auto}}
@media(prefers-reduced-motion:reduce){.pm-drawer-panel,.pm-drawer-backdrop{animation:none}.pm-chips{scroll-behavior:auto}}
</style>
@endpush
@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
const initializeProductSearch = () => {
    const input = document.getElementById('product-search');
    const dropdown = document.querySelector('.product-search-dropdown');
    const panel = document.getElementById('product-search-options');
    const empty = panel?.querySelector('.product-search-empty');
    const options = [...(panel?.querySelectorAll('.product-search-option') ?? [])];
    let activeIndex = -1;
    if (!input || !dropdown || !panel || input.dataset.productSearchBound === 'true') return;
    input.dataset.productSearchBound = 'true';

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
};

const initializeProductCreateToggle = () => {
    const toggle = document.getElementById('product-create-toggle');
    const panel = document.getElementById('product-create-panel');
    if (!toggle || !panel || toggle.dataset.productToggleBound === 'true') return;
    toggle.dataset.productToggleBound = 'true';

    toggle.addEventListener('click', () => {
        const opening = panel.hidden;
        panel.hidden = !opening;
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        toggle.querySelector('span').textContent = opening ? 'Close Form' : 'Add Product';
        if (opening) {
            panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            panel.querySelector('#name')?.focus({ preventScroll: true });
        }
    });
};

const toPascalCase = value => value
    .toLocaleLowerCase()
    .replace(/(^|[^\p{L}\p{M}\p{N}'’])(\p{L})/gu, (match, boundary, letter) => boundary + letter.toLocaleUpperCase());

const setCategoryPickerOpen = (picker, open) => {
    const panel = picker.querySelector('.category-picker-options');
    panel.hidden = !open;
    picker.querySelectorAll('[aria-expanded]').forEach(control => control.setAttribute('aria-expanded', String(open)));
    if (!open) panel.querySelectorAll('.is-active').forEach(option => option.classList.remove('is-active'));
};

const initializeCategoryPickers = () => {
    const template = document.getElementById('category-options-template');
    if (!template) return;

    document.querySelectorAll('.category-picker').forEach(picker => {
        if (picker.dataset.categoryPickerBound === 'true') return;
        picker.dataset.categoryPickerBound = 'true';

        const input = picker.querySelector('.category-picker-input');
        const toggle = picker.querySelector('.category-picker-toggle');
        const panel = picker.querySelector('.category-picker-options');
        let options = [];
        let empty = null;
        let activeIndex = -1;

        const ensureOptions = () => {
            if (options.length || empty) return;
            panel.append(template.content.cloneNode(true));
            options = [...panel.querySelectorAll('.product-search-option')];
            empty = panel.querySelector('.product-search-empty');
            options.forEach(option => {
                option.addEventListener('mousedown', event => event.preventDefault());
                option.addEventListener('click', () => selectOption(option));
            });
        };
        const visibleOptions = () => options.filter(option => !option.hidden);
        const setActive = index => {
            const visible = visibleOptions();
            options.forEach(option => option.classList.remove('is-active'));
            activeIndex = visible.length ? Math.max(-1, Math.min(index, visible.length - 1)) : -1;
            if (activeIndex >= 0) {
                visible[activeIndex].classList.add('is-active');
                visible[activeIndex].scrollIntoView({ block: 'nearest' });
            }
        };
        const showOptions = query => {
            ensureOptions();
            const normalized = query.trim().toLocaleLowerCase();
            let matches = 0;
            options.forEach(option => {
                option.hidden = normalized !== '' && !option.dataset.category.toLocaleLowerCase().includes(normalized);
                if (!option.hidden) matches++;
            });
            if (empty) empty.hidden = matches > 0 || normalized === '';
            document.querySelectorAll('.category-picker').forEach(other => {
                if (other !== picker) setCategoryPickerOpen(other, false);
            });
            setActive(-1);
            setCategoryPickerOpen(picker, true);
        };
        const selectOption = option => {
            input.value = option.dataset.category;
            setCategoryPickerOpen(picker, false);
            activeIndex = -1;
            input.focus();
        };

        input.addEventListener('focus', () => showOptions(''));
        input.addEventListener('click', () => { if (panel.hidden) showOptions(''); });
        input.addEventListener('input', () => showOptions(input.value));
        toggle.addEventListener('mousedown', event => event.preventDefault());
        toggle.addEventListener('click', () => {
            if (panel.hidden) showOptions('');
            else setCategoryPickerOpen(picker, false);
            input.focus();
        });
        input.addEventListener('keydown', event => {
            const visible = visibleOptions();
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (panel.hidden) showOptions('');
                setActive(event.key === 'ArrowDown' ? activeIndex + 1 : (activeIndex <= 0 ? visible.length - 1 : activeIndex - 1));
            } else if (event.key === 'Enter' && !panel.hidden && activeIndex >= 0) {
                event.preventDefault();
                selectOption(visible[activeIndex]);
            } else if (event.key === 'Escape' || event.key === 'Tab') {
                if (!panel.hidden) event.stopPropagation();
                setCategoryPickerOpen(picker, false);
                activeIndex = -1;
            }
        });
    });
};

// Filters, sorting, layout and selection live outside the replaced markup so they survive the AJAX re-render.
const productStatuses = ['all', 'enabled', 'disabled', 'low', 'noimage'];
const productSorters = {
    name: (a, b) => a.dataset.name.localeCompare(b.dataset.name, undefined, { sensitivity: 'base' }),
    'price-asc': (a, b) => Number(a.dataset.price) - Number(b.dataset.price),
    'price-desc': (a, b) => Number(b.dataset.price) - Number(a.dataset.price),
    'stock-asc': (a, b) => Number(a.dataset.stock) - Number(b.dataset.stock),
    updated: (a, b) => Number(b.dataset.updated) - Number(a.dataset.updated),
};
const initialProductParams = new URLSearchParams(window.location.search);
const productView = {
    category: initialProductParams.get('category') ?? '',
    status: productStatuses.includes(initialProductParams.get('status')) ? initialProductParams.get('status') : 'all',
    sort: Object.hasOwn(productSorters, initialProductParams.get('sort') ?? '') ? initialProductParams.get('sort') : 'menu',
    view: 'grid',
    selecting: false,
    selected: new Set(),
};
try { productView.view = localStorage.getItem('products.view') === 'list' ? 'list' : 'grid'; } catch (error) {}

const productMatchesStatus = (item, status) => {
    const enabled = item.dataset.active === '1';

    if (status === 'disabled') return !enabled;
    if (!enabled) return false;

    return status === 'all'
        || status === 'enabled'
        || (status === 'low' && item.dataset.low === '1')
        || (status === 'noimage' && item.dataset.image === '0');
};

const arrangeProductCards = catalog => {
    const grids = [...catalog.querySelectorAll('[data-pm-group] .pm-grid')];
    const flatGrid = catalog.querySelector('[data-pm-flat] .pm-grid');
    const byMenuOrder = (a, b) => Number(a.dataset.order) - Number(b.dataset.order);
    const items = [...catalog.querySelectorAll('[data-pm-item]')];
    if (productView.sort === 'menu' || !flatGrid) {
        items.sort(byMenuOrder).forEach(item => grids[Number(item.dataset.group)]?.append(item));
    } else {
        const sorter = productSorters[productView.sort];
        items.sort((a, b) => sorter(a, b) || byMenuOrder(a, b)).forEach(item => flatGrid.append(item));
    }
    catalog.dataset.arrangedSort = productView.sort;
};

const syncProductUrl = () => {
    const url = new URL(window.location.href);
    const params = { category: [productView.category, ''], status: [productView.status, 'all'], sort: [productView.sort, 'menu'] };
    Object.entries(params).forEach(([key, [value, fallback]]) => {
        if (value && value !== fallback) url.searchParams.set(key, value);
        else url.searchParams.delete(key);
    });
    if (url.href !== window.location.href) window.history.replaceState(window.history.state, '', url);
};

const renderProductSelection = () => {
    const catalog = document.querySelector('[data-pm-catalog]');
    const bar = document.querySelector('[data-pm-bulkbar]');
    if (!catalog) return;
    catalog.toggleAttribute('data-selecting', productView.selecting);
    catalog.querySelectorAll('[data-pm-item]').forEach(item => {
        const selected = productView.selecting && productView.selected.has(item.dataset.productId);
        item.classList.toggle('is-selected', selected);
        const checkbox = item.querySelector('[data-pm-select]');
        if (checkbox) checkbox.checked = selected;
    });
    document.querySelector('.pm-select-toggle')?.setAttribute('aria-pressed', String(productView.selecting));
    if (!bar) return;
    const count = productView.selected.size;
    bar.hidden = !productView.selecting;
    bar.querySelector('[data-pm-selected-count]').textContent = `${count} selected`;
    bar.querySelectorAll('[data-pm-bulk], [data-pm-bulk-input]').forEach(control => { control.disabled = count === 0; });
    // The move form posts like the other product forms, so the selected ids ride along as hidden fields.
    bar.querySelector('[data-pm-move-ids]')?.replaceChildren(...[...productView.selected].map(id => Object.assign(document.createElement('input'), { type: 'hidden', name: 'ids[]', value: id })));
};

// Category chips sit on one swipeable row: touch swipes natively, a mouse can drag or use the arrows.
const syncChipRail = rail => {
    const chips = rail.querySelector('.pm-chips');
    if (!chips) return;
    const max = chips.scrollWidth - chips.clientWidth;
    rail.toggleAttribute('data-at-start', chips.scrollLeft <= 1);
    rail.toggleAttribute('data-at-end', chips.scrollLeft >= max - 1);
};

const revealActiveChip = rail => {
    const chips = rail.querySelector('.pm-chips');
    const chip = chips?.querySelector('[data-pm-category][aria-pressed="true"]');
    if (!chip) return;
    const pad = chips.clientWidth > 300 ? 44 : 0;
    if (chip.offsetLeft - pad < chips.scrollLeft) chips.scrollTo({ left: chip.offsetLeft - pad });
    else if (chip.offsetLeft + chip.offsetWidth + pad > chips.scrollLeft + chips.clientWidth) chips.scrollTo({ left: chip.offsetLeft + chip.offsetWidth + pad - chips.clientWidth });
};

const chipDrag = { chips: null, startX: 0, startScroll: 0, moved: false };
document.addEventListener('pointerdown', event => {
    const chips = event.target.closest?.('.pm-chips');
    if (!chips || event.pointerType !== 'mouse' || event.button !== 0) return;
    Object.assign(chipDrag, { chips, startX: event.clientX, startScroll: chips.scrollLeft, moved: false });
});
document.addEventListener('pointermove', event => {
    const { chips } = chipDrag;
    if (!chips) return;
    const distance = event.clientX - chipDrag.startX;
    if (!chipDrag.moved && Math.abs(distance) < 6) return;
    chipDrag.moved = true;
    chips.classList.add('is-dragging');
    chips.scrollLeft = chipDrag.startScroll - distance;
});
const endChipDrag = () => {
    if (!chipDrag.chips) return;
    chipDrag.chips.classList.remove('is-dragging');
    chipDrag.chips = null;
};
document.addEventListener('pointerup', endChipDrag);
document.addEventListener('pointercancel', endChipDrag);
// A drag should only scroll, never also pick the chip it started on.
document.addEventListener('click', event => {
    if (!chipDrag.moved || !event.target.closest?.('.pm-chips')) return;
    chipDrag.moved = false;
    event.preventDefault();
    event.stopImmediatePropagation();
}, true);
document.addEventListener('scroll', event => {
    if (event.target.matches?.('.pm-chips')) syncChipRail(event.target.closest('[data-pm-chip-rail]'));
}, true);
document.addEventListener('wheel', event => {
    const chips = event.target.closest?.('.pm-chips');
    if (!chips || Math.abs(event.deltaX) >= Math.abs(event.deltaY)) return;
    const max = chips.scrollWidth - chips.clientWidth;
    if (max <= 0 || (event.deltaY < 0 && chips.scrollLeft <= 0) || (event.deltaY > 0 && chips.scrollLeft >= max)) return;
    event.preventDefault();
    chips.scrollBy({ left: event.deltaY, behavior: 'auto' });
}, { passive: false });
window.addEventListener('resize', () => document.querySelectorAll('[data-pm-chip-rail]').forEach(syncChipRail));

const applyProductFilters = () => {
    const catalog = document.querySelector('[data-pm-catalog]');
    if (!catalog) return;
    const items = [...catalog.querySelectorAll('[data-pm-item]')];
    if (productView.category && !items.some(item => item.dataset.category === productView.category)) productView.category = '';
    if (catalog.dataset.arrangedSort !== productView.sort) arrangeProductCards(catalog);

    const grouped = productView.sort === 'menu';
    let visible = 0;
    items.forEach(item => {
        const matchesCategory = !productView.category || item.dataset.category === productView.category;
        item.hidden = !(matchesCategory && productMatchesStatus(item, productView.status));
        if (item.hidden) productView.selected.delete(item.dataset.productId);
        else visible++;
    });
    catalog.querySelectorAll('[data-pm-group]').forEach(group => {
        group.hidden = !grouped || !group.querySelector('[data-pm-item]:not([hidden])');
    });
    const flat = catalog.querySelector('[data-pm-flat]');
    if (flat) {
        flat.hidden = grouped || visible === 0;
        const sortLabel = document.querySelector(`[data-pm-sort] option[value="${productView.sort}"]`)?.textContent ?? '';
        flat.querySelector('[data-pm-flat-title]').textContent = productView.category || 'All products';
        flat.querySelector('[data-pm-flat-count]').textContent = `${visible} ${visible === 1 ? 'product' : 'products'} · ${sortLabel}`;
    }
    catalog.dataset.view = productView.view;
    document.querySelectorAll('[data-pm-category]').forEach(chip => chip.setAttribute('aria-pressed', String(chip.dataset.pmCategory === productView.category)));
    document.querySelectorAll('[data-pm-status]').forEach(tile => tile.setAttribute('aria-pressed', String(tile.dataset.pmStatus === productView.status)));
    document.querySelectorAll('[data-pm-view]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.pmView === productView.view)));
    const sortSelect = document.querySelector('[data-pm-sort]');
    if (sortSelect) sortSelect.value = productView.sort;
    const empty = catalog.querySelector('[data-pm-empty]');
    if (empty) empty.hidden = !items.length || visible > 0;
    renderProductSelection();
    syncProductUrl();
};

const refreshProductCounts = () => {
    const items = [...document.querySelectorAll('[data-pm-item]')];
    document.querySelectorAll('[data-pm-status]').forEach(tile => {
        const value = tile.querySelector('strong');
        if (value) value.textContent = String(items.filter(item => productMatchesStatus(item, tile.dataset.pmStatus)).length);
    });
};

const updateLowStockBadge = count => {
    const badge = document.getElementById('low-stock-badge');
    if (!badge || !Number.isInteger(count)) return;
    const noun = count === 1 ? 'product' : 'products';
    badge.textContent = String(count);
    badge.hidden = count === 0;
    badge.setAttribute('aria-label', `${count} low-stock ${noun}`);
    badge.title = `${count} ${noun} at {{ $lowThreshold }} units or below`;
};

const setProductCardActive = (card, active) => {
    card.dataset.active = active ? '1' : '0';
    card.classList.toggle('is-hidden', !active);
    const flag = card.querySelector('[data-pm-flag]');
    if (flag) flag.hidden = active;
    card.querySelector('[data-pm-visibility]')?.setAttribute('aria-checked', String(active));
    const label = card.querySelector('[data-pm-toggle-label]');
    if (label) label.textContent = active ? 'Enabled' : 'Disabled';
};

const sendProductVisibility = async (url, fields) => {
    const body = new FormData();
    body.append('_method', 'PATCH');
    Object.entries(fields).forEach(([key, value]) => {
        if (Array.isArray(value)) value.forEach(item => body.append(`${key}[]`, item));
        else body.append(key, value);
    });
    const response = await fetch(url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
    });
    const payload = await response.json().catch(() => ({}));
    if (response.status === 419) throw new Error('Your session expired. Refresh the page and try again.');
    if (!response.ok) {
        const firstError = Object.values(payload.errors ?? {})[0]?.[0];
        throw new Error(firstError || payload.message || 'The change could not be saved. Please try again.');
    }
    return payload;
};

const applyVisibilityPayload = payload => {
    const ids = new Set((payload.ids ?? []).map(String));
    document.querySelectorAll('[data-pm-item]').forEach(card => {
        if (ids.has(card.dataset.productId)) setProductCardActive(card, Boolean(payload.active));
    });
    refreshProductCounts();
    updateLowStockBadge(payload.low_stock_count);
    applyProductFilters();
};

// A visibility change immediately moves the card into the matching Enabled or Disabled view.
const toggleProductVisibility = async toggle => {
    const card = toggle.closest('[data-pm-item]');
    if (!card || toggle.getAttribute('aria-busy') === 'true') return;
    const nextActive = card.dataset.active !== '1';
    toggle.setAttribute('aria-busy', 'true');
    setProductCardActive(card, nextActive);
    try {
        const payload = await sendProductVisibility(card.dataset.visibilityUrl, { active: nextActive ? 1 : 0 });
        applyVisibilityPayload(payload);
        const live = document.querySelector('[data-pm-live]');
        if (live) live.textContent = payload.message ?? '';
    } catch (error) {
        setProductCardActive(card, !nextActive);
        showProductToast(error.message, true);
    } finally {
        toggle.removeAttribute('aria-busy');
    }
};

const runBulkVisibility = async button => {
    const bar = button.closest('[data-pm-bulkbar]');
    const ids = [...productView.selected];
    if (!bar || !ids.length) return;
    bar.querySelectorAll('button').forEach(control => { control.disabled = true; });
    try {
        const payload = await sendProductVisibility(bar.dataset.url, { ids, active: button.dataset.pmBulk === '1' ? 1 : 0 });
        applyVisibilityPayload(payload);
        productView.selected.clear();
        showProductToast(payload.message);
    } catch (error) {
        showProductToast(error.message, true);
    } finally {
        bar.querySelectorAll('button').forEach(control => { control.disabled = false; });
        applyProductFilters();
    }
};

// One shared editor is filled from the clicked card's data attributes.
const fillProductEditor = (drawer, card) => {
    const data = card.dataset;
    const form = drawer.querySelector('#product-edit-form');
    const field = name => form.elements.namedItem(name);
    drawer.querySelectorAll('[data-pm-image-input]').forEach(resetImagePreview);
    drawer.querySelectorAll('.product-ajax-inline-error').forEach(error => error.remove());
    drawer.dataset.productId = data.productId;
    drawer.querySelector('[data-pm-editor-title]').textContent = data.name;
    form.action = data.updateUrl;
    field('form_context').value = `edit-${data.productId}`;
    field('name').value = data.name;
    field('category').value = data.category;
    field('price').value = data.price;
    drawer.querySelector('[data-pm-editor-stock]').textContent = `${data.stock} ${Number(data.stock) === 1 ? 'unit' : 'units'}`;
    field('description').value = data.description ?? '';
    field('active').checked = data.active === '1';
    field('remove_image').checked = false;

    const preview = drawer.querySelector('[data-pm-preview]');
    if (data.imageUrl) {
        const image = document.createElement('img');
        image.className = 'product-image';
        image.alt = '';
        image.src = data.imageUrl;
        preview.replaceChildren(image);
    } else {
        const placeholder = document.createElement('span');
        placeholder.className = 'pm-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        placeholder.textContent = [...data.name][0]?.toLocaleUpperCase() ?? '';
        preview.replaceChildren(placeholder);
    }
    drawer.querySelector('[data-pm-remove-image]').hidden = !data.imageUrl;
    drawer.querySelector('[data-pm-upload-label]').textContent = data.imageUrl ? 'Replace Picture' : 'Add Picture';
    const adjust = drawer.querySelector('[data-pm-crop-open]');
    if (data.imageUrl) adjust.dataset.sourceUrl = data.imageUrl;
    else delete adjust.dataset.sourceUrl;
    adjust.hidden = !data.imageUrl;
    const deleteForm = drawer.querySelector('[data-pm-delete-form]');
    if (deleteForm && data.destroyUrl) deleteForm.action = data.destroyUrl;
};

let productDrawerTrigger = null;
const openProductDrawer = (drawer, trigger = null) => {
    productDrawerTrigger = trigger;
    drawer.hidden = false;
    document.body.classList.add('pm-drawer-open');
    drawer.querySelector('input.control:not([type="hidden"])')?.focus({ preventScroll: true });
};
const closeProductDrawer = drawer => {
    drawer.hidden = true;
    drawer.querySelectorAll('form').forEach(form => form.reset());
    drawer.querySelectorAll('.product-ajax-inline-error').forEach(error => error.remove());
    drawer.querySelectorAll('[data-pm-image-input]').forEach(resetImagePreview);
    document.querySelectorAll('.category-picker').forEach(picker => setCategoryPickerOpen(picker, false));
    if (!document.querySelector('[data-pm-drawer]:not([hidden])')) document.body.classList.remove('pm-drawer-open');
    if (productDrawerTrigger?.isConnected) productDrawerTrigger.focus({ preventScroll: true });
    productDrawerTrigger = null;
};

// Picture cropper: the admin frames the picture, and the square result is uploaded ready-made.
// Previews use data: URLs because the Content-Security-Policy does not allow blob: images.
const CROP_OUTPUT = 1200;
const CROP_FILL = {{ \App\Services\ProductImageProcessor::FILL }};
const CROP_TOLERANCE = 28;
const cropSources = new WeakMap();
const crop = { input: null, source: null, image: null, work: null, box: null, turns: 0, scale: 1, x: 0, y: 0, fresh: false, previous: null, trigger: null, pointers: new Map(), pinch: 0, frame: 0, token: 0 };

const imagePreviewParts = input => {
    const field = input.closest('.pm-photo-field');
    return {
        preview: field?.querySelector('[data-pm-preview]'),
        fileName: field?.querySelector('[data-pm-file-name]'),
        adjust: field?.querySelector('[data-pm-crop-open]'),
        framed: field?.querySelector('[data-pm-image-framed]'),
    };
};
const showImagePreview = (input, source, label) => {
    const { preview, fileName } = imagePreviewParts(input);
    if (preview) {
        if (preview.dataset.original === undefined) preview.dataset.original = preview.innerHTML;
        const image = document.createElement('img');
        image.className = 'product-image';
        image.alt = '';
        image.src = source;
        preview.replaceChildren(image);
    }
    if (fileName) {
        if (fileName.dataset.original === undefined) fileName.dataset.original = fileName.textContent;
        fileName.textContent = label;
    }
};
const resetImagePreview = input => {
    const { preview, fileName, adjust, framed } = imagePreviewParts(input);
    if (preview?.dataset.original !== undefined) {
        preview.innerHTML = preview.dataset.original;
        delete preview.dataset.original;
    }
    if (fileName?.dataset.original !== undefined) {
        fileName.textContent = fileName.dataset.original;
        delete fileName.dataset.original;
    }
    if (framed) framed.value = '0';
    if (adjust) adjust.hidden = !adjust.dataset.sourceUrl;
    cropSources.delete(input);
};
const setInputFile = (input, file) => {
    try {
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        return input.files?.length === 1;
    } catch (error) {
        return false;
    }
};

const readFileAsDataUrl = file => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => reject(new Error('This picture could not be read.'));
    reader.readAsDataURL(file);
});
const loadCropImage = source => new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error('This picture could not be opened. Try a JPG, PNG or WebP file.'));
    image.src = source;
});

// Mirrors ProductImageProcessor: a plain studio background is shifted to pure white and the dish's box is measured.
const whitenCropBackground = (canvas, context) => {
    const { width, height } = canvas;
    const pixels = context.getImageData(0, 0, width, height);
    const data = pixels.data;
    const at = (x, y) => {
        const index = (y * width + x) * 4;
        return [data[index], data[index + 1], data[index + 2]];
    };
    const distance = (a, b) => Math.max(Math.abs(a[0] - b[0]), Math.abs(a[1] - b[1]), Math.abs(a[2] - b[2]));
    const corners = [at(0, 0), at(width - 1, 0), at(0, height - 1), at(width - 1, height - 1)];
    if (corners.some(corner => distance(corner, corners[0]) > CROP_TOLERANCE || Math.min(...corner) < 200)) return null;

    const background = [0, 1, 2].map(channel => Math.round(corners.reduce((sum, corner) => sum + corner[channel], 0) / 4));
    const step = Math.max(1, Math.floor(Math.max(width, height) / 300));
    let minX = width;
    let minY = height;
    let maxX = -1;
    let maxY = -1;
    for (let y = 0; y < height; y += step) {
        for (let x = 0; x < width; x += step) {
            if (distance(at(x, y), background) <= CROP_TOLERANCE) continue;
            minX = Math.min(minX, x);
            maxX = Math.max(maxX, x);
            minY = Math.min(minY, y);
            maxY = Math.max(maxY, y);
        }
    }
    if (background.some(value => value < 255)) {
        const curves = background.map(channel => Array.from({ length: 256 }, (_, value) => Math.min(255, Math.round(value * 255 / channel))));
        for (let index = 0; index < data.length; index += 4) {
            data[index] = curves[0][data[index]];
            data[index + 1] = curves[1][data[index + 1]];
            data[index + 2] = curves[2][data[index + 2]];
        }
        context.putImageData(pixels, 0, 0);
    }
    if (maxX < 0) return null;
    const x = Math.max(0, minX - step);
    const y = Math.max(0, minY - step);
    return { x, y, width: Math.min(width, maxX + 2 * step) - x, height: Math.min(height, maxY + 2 * step) - y };
};

// The working copy is capped at 2400px, rotated in quarter turns and flattened onto white.
const rebuildCropWork = () => {
    const { image, turns } = crop;
    const ratio = Math.min(1, 2400 / Math.max(image.naturalWidth, image.naturalHeight));
    const width = Math.max(1, Math.round(image.naturalWidth * ratio));
    const height = Math.max(1, Math.round(image.naturalHeight * ratio));
    const canvas = document.createElement('canvas');
    canvas.width = turns % 2 ? height : width;
    canvas.height = turns % 2 ? width : height;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.translate(canvas.width / 2, canvas.height / 2);
    context.rotate(turns * Math.PI / 2);
    context.drawImage(image, -width / 2, -height / 2, width, height);
    context.setTransform(1, 0, 0, 1, 0, 0);
    crop.work = canvas;
    crop.box = whitenCropBackground(canvas, context);
};

// Views are in frame units: the square frame is 1 wide, so the same view draws on screen and in the output.
const cropPresetView = preset => {
    const { width, height } = crop.work;
    if (preset === 'standard' && crop.box) {
        const scale = CROP_FILL / Math.max(crop.box.width, crop.box.height);
        return { scale, x: 0.5 - (crop.box.x + crop.box.width / 2) * scale, y: 0.5 - (crop.box.y + crop.box.height / 2) * scale };
    }
    const scale = preset === 'fit' ? 1 / Math.max(width, height) : 1 / Math.min(width, height);
    return { scale, x: (1 - width * scale) / 2, y: (1 - height * scale) / 2 };
};
const cropScaleLimits = () => {
    const fit = 1 / Math.max(crop.work.width, crop.work.height);
    const fill = 1 / Math.min(crop.work.width, crop.work.height);
    const standard = crop.box ? CROP_FILL / Math.max(crop.box.width, crop.box.height) : fill;
    return { min: fit * 0.5, max: Math.max(fill, standard) * 4 };
};
const setCropView = ({ scale, x, y }) => {
    const { min, max } = cropScaleLimits();
    crop.scale = Math.min(max, Math.max(min, scale));
    const width = crop.work.width * crop.scale;
    const height = crop.work.height * crop.scale;
    // Keep the picture's center inside the frame so it can never be dragged out of reach.
    crop.x = Math.min(1 - width / 2, Math.max(-width / 2, x));
    crop.y = Math.min(1 - height / 2, Math.max(-height / 2, y));
    scheduleCropRender();
};
const zoomCropAt = (scale, pointX = 0.5, pointY = 0.5) => {
    const { min, max } = cropScaleLimits();
    const next = Math.min(max, Math.max(min, scale));
    const ratio = next / crop.scale;
    setCropView({ scale: next, x: pointX - (pointX - crop.x) * ratio, y: pointY - (pointY - crop.y) * ratio });
};
const cropSliderValue = () => {
    const { min, max } = cropScaleLimits();
    return Math.log(crop.scale / min) / Math.log(max / min);
};

const drawCrop = (context, size) => {
    context.setTransform(1, 0, 0, 1, 0, 0);
    context.fillStyle = '#fff';
    context.fillRect(0, 0, size, size);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(crop.work, crop.x * size, crop.y * size, crop.work.width * crop.scale * size, crop.work.height * crop.scale * size);
};
const renderCrop = () => {
    crop.frame = 0;
    const root = document.querySelector('[data-pm-cropper]');
    const canvas = root?.querySelector('[data-pm-crop-canvas]');
    if (!canvas || !crop.work) return;
    const size = Math.round(canvas.clientWidth * (window.devicePixelRatio || 1));
    if (!size) return;
    if (canvas.width !== size) {
        canvas.width = size;
        canvas.height = size;
    }
    drawCrop(canvas.getContext('2d'), size);
    const slider = root.querySelector('[data-pm-crop-zoom]');
    if (slider && document.activeElement !== slider) slider.value = String(cropSliderValue());
};
const scheduleCropRender = () => {
    if (!crop.frame) crop.frame = window.requestAnimationFrame(renderCrop);
};

const openCropper = async (input, source, { fresh = false, trigger = null } = {}) => {
    const root = document.querySelector('[data-pm-cropper]');
    if (!root) return;
    const token = ++crop.token;
    Object.assign(crop, { input, source, fresh, trigger, image: null, work: null, box: null, turns: 0 });
    const status = root.querySelector('[data-pm-crop-status]');
    const apply = root.querySelector('[data-pm-crop-apply]');
    status.textContent = 'Loading picture…';
    status.hidden = false;
    apply.disabled = true;
    root.hidden = false;
    document.body.classList.add('pm-cropper-open');
    root.querySelector('[data-pm-crop-stage]').focus({ preventScroll: true });
    try {
        const image = await loadCropImage(source instanceof File ? await readFileAsDataUrl(source) : source);
        if (token !== crop.token) return;
        crop.image = image;
        rebuildCropWork();
        setCropView(cropPresetView('standard'));
        status.hidden = true;
        apply.disabled = false;
    } catch (error) {
        if (token === crop.token) status.textContent = error.message;
    }
};

const closeCropper = ({ applied = false } = {}) => {
    const root = document.querySelector('[data-pm-cropper]');
    if (root) root.hidden = true;
    crop.token++;
    document.body.classList.remove('pm-cropper-open');
    const { input, fresh, previous, trigger } = crop;
    // Cancelling a newly chosen file puts back whatever was there before it.
    if (!applied && fresh && input) {
        if (previous && setInputFile(input, previous.file)) {
            showImagePreview(input, previous.previewUrl, previous.label);
            imagePreviewParts(input).framed.value = '1';
            cropSources.set(input, previous);
        } else {
            input.value = '';
            resetImagePreview(input);
        }
    }
    (trigger?.isConnected ? trigger : input)?.focus({ preventScroll: true });
    Object.assign(crop, { input: null, source: null, image: null, work: null, box: null, previous: null, trigger: null, fresh: false, pinch: 0 });
    crop.pointers.clear();
};

const applyCrop = async () => {
    const { input, source } = crop;
    if (!input || !crop.work) return;
    const output = document.createElement('canvas');
    output.width = CROP_OUTPUT;
    output.height = CROP_OUTPUT;
    drawCrop(output.getContext('2d'), CROP_OUTPUT);
    const blob = await new Promise(resolve => output.toBlob(resolve, 'image/jpeg', 0.92));
    const baseName = source instanceof File ? source.name.replace(/\.[^.]+$/, '') : 'picture';
    const file = blob ? new File([blob], `${baseName}-framed.jpg`, { type: 'image/jpeg' }) : null;
    if (!file || !setInputFile(input, file)) {
        closeCropper({ applied: true });
        showProductToast("This browser can't save your framing, so the picture will be framed automatically.", true);
        return;
    }
    const previewUrl = output.toDataURL('image/jpeg', 0.85);
    const label = source instanceof File ? `${source.name} · framed by you` : 'Current picture · re-framed by you';
    const { adjust, framed } = imagePreviewParts(input);
    showImagePreview(input, previewUrl, label);
    if (framed) framed.value = '1';
    if (adjust) adjust.hidden = false;
    cropSources.set(input, { source, file, previewUrl, label });
    closeCropper({ applied: true });
};

const trapFocus = (event, container) => {
    const focusable = [...container.querySelectorAll('button, [href], input:not([type="hidden"]), textarea, select, [tabindex]:not([tabindex="-1"])')]
        .filter(element => !element.disabled && element.getClientRects().length);
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
};

// Category management: each row reveals a "move its products to" form before the category is deleted.
let categoriesTrigger = null;
const closeCategories = () => {
    const dialog = document.querySelector('[data-pm-categories]');
    if (!dialog || dialog.hidden) return;
    dialog.hidden = true;
    dialog.querySelectorAll('.pm-category-delete').forEach(form => { form.hidden = true; });
    dialog.querySelectorAll('[data-pm-category-delete]').forEach(button => button.setAttribute('aria-expanded', 'false'));
    document.body.classList.remove('pm-cropper-open');
    if (categoriesTrigger?.isConnected) categoriesTrigger.focus({ preventScroll: true });
    categoriesTrigger = null;
};
document.addEventListener('click', event => {
    const opener = event.target.closest('[data-pm-categories-open]');
    if (opener) {
        const dialog = document.querySelector('[data-pm-categories]');
        if (!dialog) return;
        categoriesTrigger = opener;
        dialog.hidden = false;
        document.body.classList.add('pm-cropper-open');
        dialog.querySelector('.pm-icon-button')?.focus({ preventScroll: true });
        return;
    }
    if (event.target.closest('[data-pm-categories-close]')) {
        closeCategories();
        return;
    }
    const toggle = event.target.closest('[data-pm-category-delete], [data-pm-category-cancel]');
    if (!toggle) return;
    const row = toggle.closest('.pm-category-row');
    const form = row.querySelector('.pm-category-delete');
    const opening = toggle.hasAttribute('data-pm-category-delete') && form.hidden;
    form.hidden = !opening;
    row.querySelector('[data-pm-category-delete]').setAttribute('aria-expanded', String(opening));
    if (opening) form.querySelector('select')?.focus();
});
document.addEventListener('keydown', event => {
    const dialog = document.querySelector('[data-pm-categories]:not([hidden])');
    if (!dialog || document.querySelector('.app-alert-layer')) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopImmediatePropagation();
        closeCategories();
    } else if (event.key === 'Tab') {
        event.stopImmediatePropagation();
        trapFocus(event, dialog.querySelector('.pm-cropper-panel'));
    }
}, true);

document.addEventListener('click', event => {
    const opener = event.target.closest('[data-pm-crop-open]');
    if (opener) {
        const input = opener.closest('.pm-photo-field')?.querySelector('[data-pm-image-input]');
        const state = input ? cropSources.get(input) : null;
        const source = state?.source ?? opener.dataset.sourceUrl;
        if (input && source) openCropper(input, source, { trigger: opener });
        return;
    }
    if (!crop.input) return;
    if (event.target.closest('[data-pm-crop-cancel]')) {
        closeCropper();
        return;
    }
    if (event.target.closest('[data-pm-crop-apply]')) {
        applyCrop();
        return;
    }
    if (!crop.work) return;
    const rotate = event.target.closest('[data-pm-crop-rotate]');
    if (rotate) {
        crop.turns = (crop.turns + Number(rotate.dataset.pmCropRotate) + 4) % 4;
        rebuildCropWork();
        setCropView(cropPresetView('standard'));
        return;
    }
    const preset = event.target.closest('[data-pm-crop-preset]');
    if (preset) setCropView(cropPresetView(preset.dataset.pmCropPreset));
});

document.addEventListener('input', event => {
    if (!event.target.matches?.('[data-pm-crop-zoom]') || !crop.work) return;
    const { min, max } = cropScaleLimits();
    zoomCropAt(min * (max / min) ** Number(event.target.value));
});

document.addEventListener('pointerdown', event => {
    const stage = event.target.closest?.('[data-pm-crop-stage]');
    if (!stage || !crop.work) return;
    stage.setPointerCapture(event.pointerId);
    crop.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    crop.pinch = 0;
});
document.addEventListener('pointermove', event => {
    const previous = crop.pointers.get(event.pointerId);
    const stage = document.querySelector('[data-pm-crop-stage]');
    if (!previous || !stage || !crop.work) return;
    const rect = stage.getBoundingClientRect();
    crop.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    if (crop.pointers.size >= 2) {
        const [a, b] = [...crop.pointers.values()];
        const spread = Math.hypot(a.x - b.x, a.y - b.y);
        if (crop.pinch) zoomCropAt(crop.scale * spread / crop.pinch, ((a.x + b.x) / 2 - rect.left) / rect.width, ((a.y + b.y) / 2 - rect.top) / rect.height);
        crop.pinch = spread;
        return;
    }
    setCropView({ scale: crop.scale, x: crop.x + (event.clientX - previous.x) / rect.width, y: crop.y + (event.clientY - previous.y) / rect.height });
});
const releaseCropPointer = event => {
    crop.pointers.delete(event.pointerId);
    crop.pinch = 0;
};
document.addEventListener('pointerup', releaseCropPointer);
document.addEventListener('pointercancel', releaseCropPointer);
document.addEventListener('wheel', event => {
    const stage = event.target.closest?.('[data-pm-crop-stage]');
    if (!stage || !crop.work) return;
    event.preventDefault();
    const rect = stage.getBoundingClientRect();
    zoomCropAt(crop.scale * Math.exp(-event.deltaY * 0.0015), (event.clientX - rect.left) / rect.width, (event.clientY - rect.top) / rect.height);
}, { passive: false });
window.addEventListener('resize', () => { if (crop.work) scheduleCropRender(); });

// Runs in the capture phase so Escape and Tab stay inside the cropper instead of reaching the edit drawer.
document.addEventListener('keydown', event => {
    const root = document.querySelector('[data-pm-cropper]:not([hidden])');
    if (!root || document.querySelector('.app-alert-layer')) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopImmediatePropagation();
        closeCropper();
        return;
    }
    if (event.key === 'Tab') {
        event.stopImmediatePropagation();
        trapFocus(event, root.querySelector('.pm-cropper-panel'));
        return;
    }
    if (!event.target.matches?.('[data-pm-crop-stage]') || !crop.work) return;
    const step = event.shiftKey ? 0.05 : 0.01;
    const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    if (moves[event.key]) {
        event.preventDefault();
        setCropView({ scale: crop.scale, x: crop.x + moves[event.key][0], y: crop.y + moves[event.key][1] });
    } else if (['+', '='].includes(event.key)) {
        event.preventDefault();
        zoomCropAt(crop.scale * 1.1);
    } else if (['-', '_'].includes(event.key)) {
        event.preventDefault();
        zoomCropAt(crop.scale / 1.1);
    }
}, true);

document.addEventListener('change', event => {
    const checkbox = event.target.closest?.('[data-pm-select]');
    if (checkbox) {
        if (checkbox.checked) productView.selected.add(checkbox.value);
        else productView.selected.delete(checkbox.value);
        renderProductSelection();
        return;
    }
    const sortSelect = event.target.closest?.('[data-pm-sort]');
    if (sortSelect) {
        productView.sort = Object.hasOwn(productSorters, sortSelect.value) ? sortSelect.value : 'menu';
        applyProductFilters();
        return;
    }
    const input = event.target.closest?.('[data-pm-image-input]');
    if (!input) return;
    const file = input.files?.[0];
    if (!file) {
        resetImagePreview(input);
        return;
    }
    // Every newly chosen picture opens in the cropper, already framed to the house standard.
    crop.previous = cropSources.get(input) ?? null;
    openCropper(input, file, { fresh: true });
});

document.addEventListener('click', event => {
    const opener = event.target.closest('[data-pm-open]');
    if (opener) {
        const drawer = document.getElementById('product-editor');
        const card = opener.closest('[data-pm-item]');
        if (drawer && card) {
            fillProductEditor(drawer, card);
            openProductDrawer(drawer, opener);
        }
        return;
    }
    const closer = event.target.closest('[data-pm-close]');
    if (closer) {
        closeProductDrawer(closer.closest('[data-pm-drawer]'));
        return;
    }
    const visibilityToggle = event.target.closest('[data-pm-visibility]');
    if (visibilityToggle) {
        toggleProductVisibility(visibilityToggle);
        return;
    }
    if (event.target.closest('[data-pm-select-mode]')) {
        productView.selecting = !productView.selecting;
        if (!productView.selecting) productView.selected.clear();
        renderProductSelection();
        return;
    }
    if (event.target.closest('[data-pm-select-all]')) {
        document.querySelectorAll('[data-pm-item]:not([hidden])').forEach(item => productView.selected.add(item.dataset.productId));
        renderProductSelection();
        return;
    }
    if (event.target.closest('[data-pm-select-none]')) {
        productView.selected.clear();
        renderProductSelection();
        return;
    }
    const bulkButton = event.target.closest('[data-pm-bulk]');
    if (bulkButton) {
        runBulkVisibility(bulkButton);
        return;
    }
    const chipScroll = event.target.closest('[data-pm-chip-scroll]');
    if (chipScroll) {
        const chips = chipScroll.closest('[data-pm-chip-rail]')?.querySelector('.pm-chips');
        chips?.scrollBy({ left: Number(chipScroll.dataset.pmChipScroll) * chips.clientWidth * 0.75 });
        return;
    }
    const chip = event.target.closest('[data-pm-category]');
    const tile = event.target.closest('[data-pm-status]');
    const viewButton = event.target.closest('[data-pm-view]');
    if (chip) productView.category = chip.dataset.pmCategory;
    if (tile) productView.status = tile.dataset.pmStatus;
    if (viewButton) {
        productView.view = viewButton.dataset.pmView;
        try { localStorage.setItem('products.view', productView.view); } catch (error) {}
    }
    if (chip || tile || viewButton) applyProductFilters();
    if (chip) revealActiveChip(chip.closest('[data-pm-chip-rail]'));
});

document.addEventListener('keydown', event => {
    const drawer = document.querySelector('[data-pm-drawer]:not([hidden])');
    if (!drawer || document.querySelector('.app-alert-layer')) return;
    if (event.key === 'Escape') {
        closeProductDrawer(drawer);
        return;
    }
    if (event.key === 'Tab') trapFocus(event, drawer);
});

const initializeProductPage = () => {
    initializeProductSearch();
    initializeProductCreateToggle();
    initializeCategoryPickers();
    applyProductFilters();
    document.querySelectorAll('[data-pm-chip-rail]').forEach(rail => {
        revealActiveChip(rail);
        syncChipRail(rail);
    });
    document.body.classList.toggle('pm-drawer-open', Boolean(document.querySelector('[data-pm-drawer]:not([hidden])')));
    document.body.classList.toggle('pm-cropper-open', Boolean(document.querySelector('[data-pm-cropper]:not([hidden]), [data-pm-categories]:not([hidden])')));
};

document.addEventListener('input', event => {
    const input = event.target.closest?.('[data-pascal-case]');
    if (!input) return;
    const { selectionStart, selectionEnd } = input;
    const formatted = toPascalCase(input.value);
    if (formatted === input.value) return;
    input.value = formatted;
    input.setSelectionRange(selectionStart, selectionEnd);
});

document.addEventListener('focusout', event => {
    const input = event.target.closest?.('[data-pascal-case]');
    if (input) input.value = toPascalCase(input.value.replace(/\s+/g, ' ').trim());
});

document.addEventListener('keydown', event => {
    if (event.target.matches?.('[data-stock-limit]') && ['e', 'E', '+', '-', '.', ','].includes(event.key)) {
        event.preventDefault();
    }
});

document.addEventListener('input', event => {
    const input = event.target.closest?.('[data-stock-limit]');
    if (!input) return;
    const digits = input.value.replace(/\D/g, '');
    if (digits === '') {
        input.value = '';
        return;
    }
    const clamped = String(Math.min(Number(digits), Number(input.max)));
    if (clamped !== input.value) input.value = clamped;
});

const showProductToast = (message, isError = false) => {
    const method = isError ? 'error' : 'success';
    window.KermitsAlert[method](message);
};

const showProductFormError = (form, message, field = '') => {
    document.querySelectorAll('.product-ajax-inline-error').forEach(error => error.remove());
    const error = document.createElement('div');
    error.className = 'product-ajax-inline-error';
    error.setAttribute('role', 'alert');
    error.textContent = message;
    form.prepend(error);
    error.scrollIntoView({ block: 'nearest' });
    if (field) form.elements.namedItem(field)?.focus();
    showProductToast(message, true);
};

document.addEventListener('click', event => {
    const current = event.target.closest('.category-picker');
    document.querySelectorAll('.category-picker').forEach(picker => {
        if (picker !== current) setCategoryPickerOpen(picker, false);
    });
});

document.addEventListener('click', event => {
    if (event.target.closest('.product-search-control')) return;
    const panel = document.getElementById('product-search-options');
    const input = document.getElementById('product-search');
    const dropdown = document.querySelector('.product-search-dropdown');
    if (!panel || panel.hidden) return;
    panel.hidden = true;
    input?.setAttribute('aria-expanded', 'false');
    dropdown?.setAttribute('aria-expanded', 'false');
});

document.addEventListener('submit', async event => {
    const form = event.target.closest('.product-ajax-form');
    if (!form || event.defaultPrevented) return;
    event.preventDefault();

    const submitButton = event.submitter ?? form.querySelector('[type="submit"]');
    const originalLabel = submitButton?.textContent;
    const savedProductId = form.closest('[data-pm-drawer]')?.dataset.productId;
    form.setAttribute('aria-busy', 'true');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = form.dataset.actionLabel || 'Saving...';
    }

    try {
        const response = await fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(),
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.status === 422) {
            const payload = await response.json();
            const firstField = Object.keys(payload.errors ?? {})[0] ?? '';
            const message = payload.errors?.[firstField]?.[0] ?? payload.message ?? 'Please check the form and try again.';
            showProductFormError(form, message, firstField);
            return;
        }

        if (!response.ok) {
            let message = 'The product could not be saved. Please try again.';
            if (response.headers.get('content-type')?.includes('application/json')) {
                const payload = await response.json();
                message = payload.message || message;
            }
            showProductFormError(form, message);
            return;
        }

        const documentFromResponse = new DOMParser().parseFromString(await response.text(), 'text/html');
        const nextDashboard = documentFromResponse.querySelector('.admin-workspace .dashboard');
        const currentDashboard = document.querySelector('.admin-workspace .dashboard');
        if (!nextDashboard || !currentDashboard) {
            showProductFormError(form, 'Your session may have expired. Refresh the page and try again.');
            return;
        }

        const serverError = nextDashboard.querySelector('.error')?.textContent.trim();
        const status = nextDashboard.querySelector('.notice')?.textContent.trim();
        if (form.hasAttribute('data-pm-bulk-move') && !serverError) productView.selected.clear();
        const workspace = document.querySelector('.admin-workspace');
        const workspaceScroll = workspace?.scrollTop ?? 0;
        const windowScroll = window.scrollY;
        currentDashboard.replaceWith(document.importNode(nextDashboard, true));
        documentFromResponse.querySelectorAll('[data-ajax-sync][id]').forEach(nextElement => {
            document.getElementById(nextElement.id)?.replaceWith(document.importNode(nextElement, true));
        });
        document.body.dataset.feedback = serverError ? 'error' : 'success';
        if (response.url && response.url !== window.location.href) {
            window.history.replaceState({}, '', response.url);
        }
        initializeProductPage();
        window.requestAnimationFrame(() => {
            if (workspace) workspace.scrollTop = workspaceScroll;
            window.scrollTo({ top: windowScroll, behavior: 'instant' });
            const savedCard = savedProductId && !serverError
                ? document.querySelector(`[data-pm-item][data-product-id="${savedProductId}"]:not([hidden])`)
                : null;
            if (savedCard) {
                savedCard.scrollIntoView({ block: 'nearest' });
                savedCard.classList.add('km-saved');
                savedCard.addEventListener('animationend', () => savedCard.classList.remove('km-saved'), { once: true });
            }
        });
        showProductToast(serverError || status || 'Product changes saved successfully.', Boolean(serverError));
    } catch (error) {
        showProductFormError(form, 'A network error occurred. Check your connection and try again.');
    } finally {
        form.removeAttribute('aria-busy');
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalLabel;
        }
    }
});

initializeProductPage();
</script>
@endpush
@endsection
