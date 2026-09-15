@props(['id' => 'recaptcha-widget'])

@if(config('services.recaptcha.enabled'))
    <div class="recaptcha-field">
        <div id="{{ $id }}" class="recaptcha-widget" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
        <p class="recaptcha-status error" role="status" aria-live="polite"></p>
        @error('g-recaptcha-response')<p class="error" role="alert">{{ $message }}</p>@enderror
        <noscript><p class="error">Please enable JavaScript to complete reCAPTCHA.</p></noscript>
    </div>

    @once
        @push('styles')
            <style>
                .recaptcha-field{margin:14px 0;max-width:100%;overflow:hidden}.recaptcha-field .error{margin:7px 0 0}.recaptcha-widget{min-height:78px}@media(max-width:370px){.recaptcha-widget{transform:scale(.86);transform-origin:top left;width:354px;margin-bottom:-11px}}
            </style>
        @endpush
        @push('scripts')
            <script>
                window.kermitsRecaptchaError = function () {
                    document.querySelectorAll('.recaptcha-status').forEach(function (status) {
                        status.textContent = 'Unable to load reCAPTCHA. Check your connection and reload this page.';
                    });
                };
                window.kermitsRecaptchaReady = function () {
                    document.querySelectorAll('.recaptcha-widget').forEach(function (container) {
                        const status = container.parentElement.querySelector('.recaptcha-status');
                        grecaptcha.render(container, {
                            sitekey: container.dataset.sitekey,
                            callback: function () { status.textContent = ''; },
                            'expired-callback': function () { status.textContent = 'reCAPTCHA expired. Please check the box again.'; },
                            'error-callback': window.kermitsRecaptchaError
                        });
                    });
                };
            </script>
            <script src="https://www.google.com/recaptcha/api.js?onload=kermitsRecaptchaReady&render=explicit" async defer onerror="kermitsRecaptchaError()"></script>
        @endpush
    @endonce
@endif
