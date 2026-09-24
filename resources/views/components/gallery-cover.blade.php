@props([
    /** @var \App\Models\Gallery */
    'gallery',
    /** @var \App\Models\Media */
    'image',
])

@use(App\Enums\CoverLayout)

@php
    $layout = $gallery->cover_layout ?? CoverLayout::Center;
    $src = route('media.show', $image);
    $date = $gallery->created_at->translatedFormat('d M Y');

    // Light layouts put dark text on the page background; the rest overlay white text on the photo.
    $light = in_array($layout, [CoverLayout::Novel, CoverLayout::Vintage], true);
    $text = $light ? 'text-zinc-800 dark:text-zinc-50' : 'text-white';
    $muted = $light ? 'text-zinc-500' : 'text-white/85';
    $button = $light
        ? 'border-zinc-800/60 text-zinc-800 hover:bg-zinc-800/5 dark:border-zinc-200/60 dark:text-zinc-100'
        : 'border-white/70 text-white hover:bg-white/10';

    $align = match ($layout) {
        CoverLayout::Left, CoverLayout::Stripe, CoverLayout::Novel => 'items-start text-left',
        default => 'items-center text-center',
    };
@endphp

<div {{ $attributes->class('relative left-1/2 right-1/2 -mx-[50vw] -mt-6 w-screen') }} data-cover-layout="{{ $layout->value }}">
    @switch ($layout)
        @case (CoverLayout::Novel)
            <div class="grid min-h-[70vh] items-center gap-10 px-6 py-12 sm:min-h-[85vh] sm:px-12 md:grid-cols-2 lg:px-24">
                <div class="order-2 flex md:order-1">@include('components.gallery-cover-content')</div>
                <img src="{{ $src }}" alt="" class="order-1 aspect-[3/4] max-h-[75vh] w-full object-cover md:order-2">
            </div>
            @break

        @case (CoverLayout::Vintage)
            <div class="flex flex-col items-center gap-10 px-6 pt-6 pb-4 sm:px-12 lg:px-24">
                <img src="{{ $src }}" alt="" class="h-[55vh] min-h-[320px] w-full object-cover sm:h-[65vh]">
                @include('components.gallery-cover-content')
            </div>
            @break

        @default
            <div class="relative h-[70vh] min-h-[420px] sm:h-[85vh]">
                <img src="{{ $src }}" alt="" class="absolute inset-0 size-full object-cover">
                <div @class([
                    'absolute inset-0',
                    'bg-linear-to-t from-black/70 via-black/20 to-black/40' => $layout !== CoverLayout::Left,
                    'bg-linear-to-tr from-black/75 via-black/25 to-transparent' => $layout === CoverLayout::Left,
                ])></div>

                @if ($layout === CoverLayout::Frame)
                    <div class="pointer-events-none absolute inset-5 border border-white/80 sm:inset-10"></div>
                @endif

                <div @class([
                    'relative flex h-full flex-col px-6',
                    'justify-center' => $layout !== CoverLayout::Left,
                    'justify-end pb-14 sm:px-16 sm:pb-20' => $layout === CoverLayout::Left,
                    'sm:px-16 lg:px-24' => $layout === CoverLayout::Stripe,
                ])>
                    @include('components.gallery-cover-content')
                </div>
            </div>
    @endswitch
</div>
