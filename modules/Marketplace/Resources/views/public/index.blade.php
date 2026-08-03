<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $meta['title'] }}</title>
    <meta name="description" content="{{ $meta['description'] }}">

    {{-- Open Graph: es lo que se ve al pegar el enlace en WhatsApp, y la razón
         de ser de la ruta /marketplace/tienda/{slug}. --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if(!empty($meta['image']))
        <meta property="og:image" content="{{ $meta['image'] }}">
    @endif
    <meta name="twitter:card" content="{{ !empty($meta['image']) ? 'summary_large_image' : 'summary' }}">

    @if(!empty($meta['noindex']))
        <meta name="robots" content="noindex">
    @endif

    {{-- Blade autónomo: no extiende system.layouts.app. El bundle es propio y
         no arrastra Element UI ni Bootstrap.
         Precedente: modules/ClaimsBook/Resources/views/widget.blade.php --}}
    @vite(['modules/Marketplace/Resources/assets/js/marketplace.js'])
</head>
<body>
    <div id="marketplace-app"></div>

    {{-- Un solo objeto con todo lo que el servidor inyecta. --}}
    <script>
        window.__marketplace = @json($boot);
    </script>
</body>
</html>
