@use(App\Enums\CoverLayout)

<div
    x-data="{ shown: false }"
    x-init="setTimeout(() => shown = true, 150)"
    class="flex w-full flex-col gap-4 {{ $align }}"
>
    @if ($layout === CoverLayout::Stripe)
        <span class="block h-px w-full max-w-xl bg-white/80"></span>
    @endif

    <h1 class="flex flex-wrap text-4xl font-bold tracking-wide uppercase sm:text-6xl {{ $text }} {{ str_contains($align, 'text-center') ? 'justify-center' : 'justify-start' }}">
        @foreach (mb_str_split($gallery->title) as $index => $letter)
            <span
                x-bind:class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'"
                style="transition: opacity 0.6s ease-out {{ $index * 0.08 }}s, transform 0.6s ease-out {{ $index * 0.08 }}s;"
                class="inline-block whitespace-pre"
            >{{ $letter }}</span>
        @endforeach
    </h1>

    <p
        x-bind:class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'"
        class="text-sm tracking-[0.25em] uppercase transition-all duration-700 ease-out [transition-delay:900ms] {{ $muted }}"
    >
        {{ $date }}
    </p>

    @if ($layout === CoverLayout::Stripe)
        <span class="block h-px w-full max-w-xl bg-white/80"></span>
    @endif

    <a
        href="#galeria"
        x-bind:class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'"
        class="mt-2 rounded border px-6 py-2 text-xs font-medium tracking-[0.2em] uppercase transition-all duration-700 ease-out [transition-delay:1150ms] {{ $button }}"
    >
        {{ __('Ver galería') }}
    </a>
</div>
