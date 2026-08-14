<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>System account setup</title>
    <style>
        *{box-sizing:border-box}body{min-height:100vh;margin:0;padding:24px;display:grid;place-items:center;background:#f4f5ee;color:#171817;font-family:Inter,system-ui,sans-serif}.setup{width:min(480px,100%)}.brand{display:flex;align-items:center;gap:12px;margin-bottom:28px}.brand img{width:52px;height:52px;border-radius:50%;background:#fff}.brand strong{letter-spacing:.1em}.panel{padding:28px;background:#fff;border:1px solid #d8dacf;border-radius:8px}.eyebrow{margin:0 0 8px;color:#747d00;font-size:12px;font-weight:800;text-transform:uppercase}.panel h1{margin:0 0 8px;font-size:26px}.muted{margin:0 0 22px;color:#6d7269;line-height:1.5}.field{display:grid;gap:7px;margin-top:15px}.field label{font-size:14px;font-weight:700}.field input{width:100%;min-height:46px;padding:10px 12px;border:1px solid #cfd3c7;border-radius:7px;font:inherit}.button{width:100%;min-height:48px;margin-top:20px;border:0;border-radius:7px;background:#171817;color:#fff;font:700 15px/1 Inter,system-ui,sans-serif;cursor:pointer}.notice,.error{padding:12px 14px;border-radius:7px;margin-bottom:16px}.notice{background:#edf7ed;color:#17652d}.error{background:#fff0f0;color:#9b1c1c}.accounts{margin:20px 0 0;padding:16px;background:#f5f6ef;border-radius:7px;line-height:1.7}.accounts code{font-size:13px}.complete{text-align:center}.complete a{display:inline-block;margin-top:12px;color:#525900;font-weight:700}
    </style>
</head>
<body>
<main class="setup">
    <div class="brand"><img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's"><strong>KERMIT'S</strong></div>
    <section class="panel">
        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        @if($complete)
            <div class="complete"><p class="eyebrow">Setup complete</p><h1>System accounts are ready</h1><p class="muted">This one-time page is locked and cannot create accounts again.</p><a href="{{ route('login') }}">Continue to login</a></div>
        @else
            <p class="eyebrow">One-time deployment</p><h1>Create system accounts</h1><p class="muted">Enter the private deployment key from your server and choose a strong password for the initial accounts.</p>
            @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('web-seeder.store') }}">@csrf
                <div class="field"><label for="deployment_key">Deployment key</label><input id="deployment_key" name="deployment_key" type="password" autocomplete="off" required></div>
                <div class="field"><label for="password">Account password</label><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div>
                <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>
                <button class="button" type="submit">Create and lock accounts</button>
            </form>
            <div class="accounts"><strong>Login usernames</strong><br><code>superadmin</code>, <code>admin</code>, <code>cashier</code>, <code>customer</code></div>
        @endif
    </section>
</main>
</body>
</html>
