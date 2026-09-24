@props([
    /** @var \Illuminate\Support\Collection<int, \App\Models\Media> */
    'media',
    'total',
    'empty',
])

@if ($media->isEmpty())
    <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-20 text-center dark:border-zinc-700">
        <flux:icon name="photo" class="size-10 text-zinc-300 dark:text-zinc-600" />
        <flux:text class="max-w-sm text-zinc-500">{{ $empty }}</flux:text>
    </div>
@else
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
        @foreach ($media as $item)
            @php $gallery = $item->album->gallery; @endphp

            <div wire:key="media-{{ $item->id }}" class="group relative aspect-square overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                <a href="{{ route('galleries.edit', $gallery) }}" wire:navigate class="absolute inset-0" title="{{ $item->original_name }}">
                    @if ($item->isVideo())
                        <video
                            src="{{ route('media.show', $item) }}#t=0.1"
                            preload="metadata"
                            muted
                            playsinline
                            class="size-full object-cover"
                        ></video>
                        <flux:icon name="play-circle" class="absolute top-1/2 left-1/2 size-10 -translate-1/2 text-white drop-shadow" />
                    @else
                        <img
                            src="{{ route('media.show', $item) }}"
                            alt="{{ $item->original_name }}"
                            loading="lazy"
                            class="size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                        >
                    @endif

                    <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-linear-to-t from-black/60 to-transparent px-3 pt-8 pb-2.5 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                        <p class="truncate text-sm font-medium text-white">{{ $gallery->title }}</p>
                        <p class="truncate text-xs text-white/75">{{ $item->album->title }}</p>
                    </div>
                </a>

                <button
                    type="button"
                    wire:click="toggleFeatured({{ $item->id }})"
                    aria-pressed="{{ $item->is_featured ? 'true' : 'false' }}"
                    title="{{ $item->is_featured ? __('Quitar de destacadas') : __('Marcar como destacada') }}"
                    @class([
                        'absolute top-2 right-2 flex size-8 cursor-pointer items-center justify-center rounded-full shadow-sm transition',
                        'bg-[#B3907A] text-white' => $item->is_featured,
                        'bg-white/90 text-zinc-700 opacity-0 group-hover:opacity-100 focus-visible:opacity-100 hover:bg-white' => ! $item->is_featured,
                    ])
                >
                    <flux:icon name="sparkles" :variant="$item->is_featured ? 'solid' : 'outline'" class="size-4" />
                </button>
            </div>
        @endforeach
    </div>

    @if ($media->count() < $total)
        <div class="flex justify-center pt-4">
            <flux:button wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                {{ __('Cargar más') }}
                <span class="text-zinc-500">({{ $media->count() }} / {{ $total }})</span>
            </flux:button>
        </div>
    @endif
@endif
