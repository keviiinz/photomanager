@php
    $pageTitle = filled($title ?? null) ? $title : ($ogGallery->title ?? null);
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($pageTitle) ? $pageTitle.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@isset($ogGallery)
    @php
        $ogDescription = __('Fotos de :client, por :photographer', [
            'client' => $ogGallery->client_name,
            'photographer' => $ogGallery->photographer->name,
        ]);
    @endphp

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $ogGallery->title }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ route('galleries.show', $ogGallery) }}">
    <meta name="twitter:title" content="{{ $ogGallery->title }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ route('media.show', $ogImage) }}">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif
@endisset

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-serif:400,400i" rel="stylesheet" />

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
