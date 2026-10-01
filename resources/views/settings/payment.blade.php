@extends('layouts.app')
@section('title', 'Payment Settings')
@section('content')
@php($qrUrl = $qrPath ? route('public.media', ['path' => $qrPath]) : null)
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace">
<div class="dashboard payment-settings">
    <header><h1>Payment Settings</h1><span>Manage the GCash QR code customers scan to pay. Table prices are set in <a href="{{ route('tables.index') }}">Table Management</a>.</span></header>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error settings-error">{{ $errors->first() }}</div>@endif
    <section class="welcome qr-settings">
        <div class="qr-settings-head"><h2>GCash QR code</h2><p>{{ $qrPath ? 'Currently shown to customers on the website and mobile app.' : 'No QR code uploaded yet. Customers see a placeholder until you add one.' }}</p></div>
        <form method="POST" action="{{ route('settings.payment.update') }}" enctype="multipart/form-data" class="qr-settings-form" data-ajax-form data-ajax-target=".payment-settings" data-ajax-loading="Uploading...">@csrf @method('PUT')
            <div class="pm-photo-field pm-photo-row qr-photo-field" data-pm-crop-default="fit" data-pm-crop-help="Drag to move. Scroll, pinch or use the slider to zoom. Keep the whole QR code inside the dashed guide so customers can scan it.">
                <div class="pm-photo-preview qr-preview" data-pm-preview>
                    @if($qrUrl)<img class="product-image" src="{{ $qrUrl }}" alt="Current GCash QR code">@else<span class="pm-photo-empty"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><circle cx="9" cy="10" r="2"></circle><path d="m21 16-5-5-8 8"></path></svg>No QR code yet</span>@endif
                </div>
                <div class="pm-photo-actions">
                    <input class="pm-file-input" id="gcash_qr" name="gcash_qr" type="file" accept="image/jpeg,image/png,image/webp" required data-pm-image-input>
                    <label class="pm-upload-button" for="gcash_qr">{{ $qrUrl ? 'Replace Picture' : 'Choose Picture' }}</label>
                    <button type="button" class="pm-adjust-button" data-pm-crop-open @if($qrUrl) data-source-url="{{ $qrUrl }}" @else hidden @endif><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 2v14a2 2 0 0 0 2 2h14"></path><path d="M18 22V8a2 2 0 0 0-2-2H2"></path></svg>Adjust framing</button>
                    <small class="pm-hint" data-pm-file-name>JPG, PNG or WebP up to 2 MB. You can move, zoom and rotate it before saving.</small>
                </div>
            </div>
            <p class="warning">Scan the new QR code with GCash and check the account name before saving.</p>
            <button class="button qr-save">Save QR code</button>
        </form>
    </section>
</div>
@include('partials.picture-cropper')
</main></div>
@push('styles')
<style>.payment-settings>header{margin-bottom:22px}.payment-settings>header h1{font-size:30px;margin:6px 0}.payment-settings>header span,.payment-settings section p{color:#687286}.qr-settings{padding:24px;max-width:760px}.qr-settings-head h2{margin:0}.qr-settings-head p{margin:6px 0 18px}.qr-settings-form{display:grid;gap:16px}.qr-photo-field.pm-photo-row{grid-template-columns:220px minmax(0,1fr)}.qr-preview img{object-fit:contain}.settings-error{background:#fff0f0;padding:12px;border-radius:9px}.warning{background:#fff8df;border-radius:9px;padding:11px;font-size:12px;margin:0}.qr-save{justify-self:start;width:auto;padding-inline:26px}@media(max-width:620px){.qr-photo-field.pm-photo-row{grid-template-columns:minmax(0,1fr)}.qr-preview{width:min(260px,100%)}.qr-save{justify-self:stretch;width:100%}}</style>
@endpush
@endsection
