<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Security check</title>
    {{-- The Android app draws the title and close button; this page only hosts the checkbox. --}}
    <style>
        :root{color-scheme:light}*{box-sizing:border-box}html,body{margin:0;background:#fff;color:#171817;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;-webkit-tap-highlight-color:transparent}.wrap{display:flex;flex-direction:column;align-items:center;padding:6px 0}.slot{position:relative;width:304px;min-height:78px}.skeleton{position:absolute;inset:0;border:1px solid #e2e4da;border-radius:4px;background:linear-gradient(90deg,#f6f7f2 25%,#eceee6 50%,#f6f7f2 75%);background-size:200% 100%;animation:shimmer 1.2s linear infinite}#mobile-recaptcha{position:relative;z-index:1}.status{margin:10px 16px 0;color:#b72c2c;font-size:13px;line-height:1.4;text-align:center}.status:empty{display:none}@keyframes shimmer{to{background-position:-200% 0}}@media(max-width:319px){.slot{transform:scale(.9);transform-origin:top center}}
    </style>
</head>
<body>
<main class="wrap">
    <div class="slot"><span class="skeleton" aria-hidden="true"></span><div id="mobile-recaptcha"></div></div>
    <p id="status" class="status" role="status" aria-live="polite"></p>
</main>
<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        var status = document.getElementById('status');
        var expanded = false;
        var challengeSeen = false;

        // The app grows the popup while Google's picture challenge is open and shrinks it afterwards.
        function layout(challenge) {
            if (challenge === expanded) return;
            expanded = challenge;
            window.location.href = 'kermits-recaptcha://layout?challenge=' + (challenge ? '1' : '0');
        }

        function challengeVisible() {
            var frame = document.querySelector('iframe[src*="/bframe"]');
            if (!frame) return false;
            var holder = frame;
            while (holder.parentElement && holder.parentElement !== document.body) holder = holder.parentElement;

            return window.getComputedStyle(holder).visibility !== 'hidden' && frame.getBoundingClientRect().height > 0;
        }

        // Tapping the checkbox moves focus into Google's frame, so the popup grows before a challenge is drawn.
        window.addEventListener('blur', function () {
            setTimeout(function () {
                if (document.activeElement && document.activeElement.tagName === 'IFRAME') layout(true);
            }, 0);
        });
        new MutationObserver(function () {
            if (challengeVisible()) {
                challengeSeen = true;
                layout(true);
            } else if (challengeSeen) {
                challengeSeen = false;
                layout(false);
            }
        }).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['style'] });

        window.mobileRecaptchaReady = function () {
            grecaptcha.render('mobile-recaptcha', {
                sitekey: @json(config('services.recaptcha.site_key')),
                callback: function (token) {
                    status.textContent = '';
                    window.location.href = 'kermits-recaptcha://success?token=' + encodeURIComponent(token);
                },
                'expired-callback': function () {
                    layout(false);
                    status.textContent = 'The check expired. Please tick the box again.';
                },
                'error-callback': function () {
                    layout(false);
                    status.textContent = 'reCAPTCHA could not load. Check your connection and try again.';
                }
            });
        };
    })();
</script>
<script nonce="{{ Vite::cspNonce() }}" src="https://www.google.com/recaptcha/api.js?onload=mobileRecaptchaReady&render=explicit" async defer></script>
</body>
</html>
