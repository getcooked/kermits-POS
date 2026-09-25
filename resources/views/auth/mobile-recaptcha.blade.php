<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Security verification</title>
    <style>
        :root{color-scheme:light}*{box-sizing:border-box}body{margin:0;background:#f7f7f1;color:#202124;font-family:Arial,sans-serif}.wrap{min-height:100vh;display:grid;place-items:center;padding:24px 12px}.card{width:min(100%,360px);background:#fff;border:1px solid #e1e3da;border-radius:18px;padding:22px 16px;text-align:center;box-shadow:0 12px 30px rgba(23,24,23,.08)}h1{font-size:21px;margin:0 0 8px}p{color:#687286;font-size:14px;line-height:1.45;margin:0 0 18px}.widget{display:flex;justify-content:center;min-height:78px}.status{margin:14px 0 0;color:#a12828;font-size:13px}.done{color:#626b00}@media(max-width:350px){.widget{transform:scale(.88);transform-origin:top center;margin-bottom:-9px}}
    </style>
</head>
<body>
<main class="wrap">
    <section class="card">
        <h1>Security verification</h1>
        <p>Complete the checkbox to continue in the Kermit's app.</p>
        <div id="mobile-recaptcha" class="widget"></div>
        <p id="status" class="status" role="status" aria-live="polite"></p>
    </section>
</main>
<script nonce="{{ Vite::cspNonce() }}">
    window.mobileRecaptchaReady = function () {
        grecaptcha.render('mobile-recaptcha', {
            sitekey: @json(config('services.recaptcha.site_key')),
            callback: function (token) {
                const status = document.getElementById('status');
                status.className = 'status done';
                status.textContent = 'Verified. Returning to the app...';
                window.location.href = 'kermits-recaptcha://success?token=' + encodeURIComponent(token);
            },
            'expired-callback': function () {
                document.getElementById('status').textContent = 'Verification expired. Please complete it again.';
            },
            'error-callback': function () {
                document.getElementById('status').textContent = 'Unable to load reCAPTCHA. Check your connection and try again.';
            }
        });
    };
</script>
<script nonce="{{ Vite::cspNonce() }}" src="https://www.google.com/recaptcha/api.js?onload=mobileRecaptchaReady&render=explicit" async defer></script>
</body>
</html>
