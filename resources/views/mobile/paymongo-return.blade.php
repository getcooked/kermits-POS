<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Return to Kermit's</title>
    <style>
        :root{color-scheme:light}*{box-sizing:border-box}body{margin:0;background:#f7f7f1;color:#202124;font-family:Arial,sans-serif}.wrap{min-height:100vh;display:grid;place-items:center;padding:24px 12px}.card{width:min(100%,360px);background:#fff;border:1px solid #e1e3da;border-radius:18px;padding:22px 16px;text-align:center;box-shadow:0 12px 30px rgba(23,24,23,.08)}h1{font-size:21px;margin:0 0 8px}p{color:#687286;font-size:14px;line-height:1.45;margin:0 0 18px}.button{display:block;background:#171817;color:#fff;border-radius:11px;padding:14px;font-weight:700;text-decoration:none}
    </style>
</head>
<body>
<main class="wrap">
    <section class="card">
        <h1>Return to the Kermit's app</h1>
        <p>Your receipt in the app shows Paid once PayMongo confirms the payment.</p>
        <a id="open-app" class="button" href="kermits://paymongo-return?order={{ $order }}">Open Kermit's app</a>
    </section>
</main>
<script nonce="{{ Vite::cspNonce() }}">
    window.location.href = document.getElementById('open-app').href;
</script>
</body>
</html>
