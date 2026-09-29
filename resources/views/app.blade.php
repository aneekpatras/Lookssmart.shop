<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Phase 13 sub-step 2: real brand accent (resources/css/app.css --color-accent-500), not a
    placeholder — tints supporting browsers' UI chrome and the PWA-lite splash/task-switcher card. --}}
    <meta name="theme-color" content="#c9a66b">
    <link rel="manifest" href="{{ route('manifest') }}">

    <title inertia>{{ config('app.name', 'Looks Smart Beauty Salon') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Preloading the font CSS itself (not a guessed woff2 URL) is the correct, stable optimization
    for a Google-Fonts-hosted stylesheet — the actual font file URLs it declares are generated
    per-user-agent by Google and aren't something we can safely hardcode a preload for. --}}
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..700&family=Inter:wght@400;500;600;700&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @routes(null, $cspNonce ?? null)
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    {{--
        Phase 13 sub-step 2: GTM's <body>-top noscript iframe fallback — the one piece of the GTM
        install that structurally cannot live in <head> (Inertia's <Head>/SeoHead.tsx only manages
        <head>), so it has to be here rather than centralized with the rest of the analytics wiring.
        Deliberately excluded from /admin* — SeoHead.tsx (public-only) already keeps the <head> GTM/GA4
        scripts off admin pages for the same reason: staff dashboard usage has no business being mixed
        into a customer-facing analytics property.
    --}}
    @php($gtmId = \App\Models\Setting::get('integrations.google_tag_manager_id'))
    @if ($gtmId && ! request()->is('admin*'))
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    @inertia
</body>
</html>
