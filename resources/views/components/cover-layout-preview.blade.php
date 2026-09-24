@props([
    /** @var \App\Enums\CoverLayout */
    'layout',
    /** Cover photo URL, or null to preview with a neutral placeholder. */
    'image' => null,
])

@use(App\Enums\CoverLayout)

@php
    $photo = $image
        ? "background-image: url('".e($image)."'); background-size: cover; background-position: center;"
        : 'background: linear-gradient(135deg, #C1B6A4, #918674);';
    $title = __('Título');
@endphp

{{-- A tiny, static mock of each cover layout (see the gallery-cover component for the real thing). --}}
<span class="relative block aspect-[4/3] w-full overflow-hidden bg-white" aria-hidden="true">
    @switch ($layout)
        @case (CoverLayout::Novel)
            <span class="absolute inset-y-[8%] right-[6%] w-[42%]" style="{{ $photo }}"></span>
            <span class="absolute top-1/2 left-[10%] -translate-y-1/2 text-[9px] font-bold tracking-[0.2em] text-zinc-800 uppercase">{{ $title }}</span>
            @break

        @case (CoverLayout::Vintage)
            <span class="absolute inset-x-[6%] top-[8%] h-[58%]" style="{{ $photo }}"></span>
            <span class="absolute bottom-[10%] left-1/2 -translate-x-1/2 text-[9px] font-bold tracking-[0.2em] text-zinc-800 uppercase">{{ $title }}</span>
            @break

        @default
            <span class="absolute inset-0" style="{{ $photo }}"></span>
            <span class="absolute inset-0 bg-black/25"></span>

            @if ($layout === CoverLayout::Frame)
                <span class="absolute inset-[8%] border border-white/90"></span>
            @endif

            @if ($layout === CoverLayout::Stripe)
                <span class="absolute inset-x-[12%] top-[26%] h-px bg-white/90"></span>
                <span class="absolute inset-x-[12%] bottom-[26%] h-px bg-white/90"></span>
            @endif

            <span @class([
                'absolute text-[9px] font-bold tracking-[0.2em] text-white uppercase',
                'top-1/2 left-1/2 -translate-1/2' => in_array($layout, [CoverLayout::Center, CoverLayout::Frame], true),
                'bottom-[12%] left-[10%]' => $layout === CoverLayout::Left,
                'top-1/2 left-[14%] -translate-y-1/2' => $layout === CoverLayout::Stripe,
            ])>{{ $title }}</span>
    @endswitch
</span>
