<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0a08">
    <meta name="description" content="SN Tasks — أهداف أكبر .. حياة أفضل. خطّط، نظّم، أنجز.">
    <title>SN Tasks</title>

    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" type="image/png" sizes="192x192" href="/images/icon-192.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    {{-- Cover shown when the link is shared (WhatsApp, Telegram, X, Facebook). --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SN Tasks">
    <meta property="og:title" content="SN Tasks — أهداف أكبر .. حياة أفضل">
    <meta property="og:description" content="خطّط، نظّم، أنجز: مساحتك اليومية للمهام والأهداف والتركيز.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ url('/images/sn-tasks-social.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ url('/images/sn-tasks-social.jpg') }}">

    @vite(['resources/js/main.ts'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
