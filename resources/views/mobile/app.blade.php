<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#171817">
    <meta name="description" content="Order from Kermit's Restaurant and manage your reservations.">
    <link rel="manifest" href="{{ asset('mobile.webmanifest') }}">
    <link rel="icon" href="{{ asset('kermits-logo.jpg') }}">
    <title>Kermit's | Customer app</title>
    @vite(['resources/css/mobile.css', 'resources/js/mobile.js'])
</head>
<body>
    <div id="app" class="mobile-app" aria-live="polite">
        <noscript><p class="noscript">JavaScript is required to use the customer app.</p></noscript>
    </div>
</body>
</html>
