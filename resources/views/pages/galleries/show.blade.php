<?php

use App\Actions\Galleries\AttemptGalleryUnlock;
use App\Enums\UnlockAttemptResult;
use App\Models\Album;
use App\Models\Gallery;
use App\Models\Media;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public')] class extends Component {
    public Gallery $gallery;

    public ?int $activeAlbumId = null;
    public string $code = '';

    /** @var array<int, int> */
    public array $selected = [];

    public ?int $lightboxMediaId = null;

    public function mount(Gallery $gallery): void
    {
        abort_unless($gallery->isVisibleTo(Auth::user()), 404);
        abort_unless($gallery->isAvailableTo(Auth::user()), 410);

        $this->gallery = $gallery;
        $this->activeAlbumId = $gallery->albums()->orderBy('position')->first()?->id;
    }

    #[Computed]
    public function isUnlocked(): bool
    {
        return $this->gallery->isUnlockedFor(Auth::user());
    }

    #[Computed]
    public function heroImage(): ?Media
    {
        return $this->gallery->publicCoverImage();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Album>
     */
    #[Computed]
    public function albums()
    {
        return $this->gallery->albums()->get();
    }

    #[Computed]
    public function activeAlbum(): ?Album
    {
        return $this->albums->firstWhere('id', $this->activeAlbumId);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Media>
     */
    #[Computed]
    public function activeAlbumMedia()
    {
        $media = $this->activeAlbum?->media()->get() ?? collect();

        // The photographer can keep the cover photo out of the grid so it isn't shown twice.
        if (! $this->gallery->show_cover_in_gallery && $this->heroImage) {
            $media = $media->reject(fn (Media $item) => $item->id === $this->heroImage->id);
        }

        if ($this->isUnlocked) {
            return $media;
        }

        $featured = $media->where('is_featured', true);
        $teasers = $media->where('is_featured', false)->filter->isTeaser();

        return $featured->merge($teasers)->values();
    }

    #[Computed]
    public function lightboxMedia(): ?Media
    {
        return $this->lightboxMediaId
            ? $this->activeAlbumMedia->firstWhere('id', $this->lightboxMediaId)
            : null;
    }

    #[Computed]
    public function lightboxIndex(): ?int
    {
        $index = $this->activeAlbumMedia->pluck('id')->search($this->lightboxMediaId);

        return $index === false ? null : $index;
    }

    public function openLightbox(int $mediaId): void
    {
        $this->lightboxMediaId = $mediaId;

        Flux::modal('lightbox')->show();
    }

    public function closeLightbox(): void
    {
        $this->lightboxMediaId = null;
    }

    public function lightboxNext(): void
    {
        $this->stepLightbox(1);
    }

    public function lightboxPrevious(): void
    {
        $this->stepLightbox(-1);
    }

    protected function stepLightbox(int $direction): void
    {
        if ($this->lightboxIndex === null) {
            return;
        }

        $ids = $this->activeAlbumMedia->pluck('id');
        $newIndex = $this->lightboxIndex + $direction;

        if ($ids->has($newIndex)) {
            $this->lightboxMediaId = $ids[$newIndex];
        }
    }

    #[Computed]
    public function selectedTotalBytes(): int
    {
        return $this->activeAlbumMedia
            ->whereIn('id', $this->selected)
            ->sum('size_bytes');
    }

    public function selectAlbum(int $albumId): void
    {
        $this->activeAlbumId = $albumId;
        $this->selected = [];
    }

    public function toggleSelected(int $mediaId): void
    {
        if (in_array($mediaId, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$mediaId]));
        } else {
            $this->selected[] = $mediaId;
        }
    }

    public function selectAllInActiveAlbum(): void
    {
        $this->selected = $this->activeAlbumMedia->pluck('id')->all();
    }

    public function unlock(): void
    {
        $result = app(AttemptGalleryUnlock::class)(
            $this->gallery,
            Auth::user(),
            $this->code,
            request()->ip(),
        );

        match ($result) {
            UnlockAttemptResult::Unlocked => Flux::toast(variant: 'success', text: __('¡Galería desbloqueada!')),
            UnlockAttemptResult::InvalidCode => $this->addError('code', __('Código incorrecto.')),
            UnlockAttemptResult::TooManyAttempts => $this->addError('code', __('Demasiados intentos, inténtalo de nuevo en un minuto.')),
        };

        $this->code = '';

        unset($this->isUnlocked, $this->activeAlbumMedia);
    }
}; ?>

<div class="mx-auto flex max-w-6xl flex-col gap-16 pb-32">
    @if ($this->heroImage)
        <x-gallery-cover :gallery="$gallery" :image="$this->heroImage" />
    @endif

    {{-- Everything below the cover spans the full window width, like the reference design. --}}
    <div id="galeria" class="relative left-1/2 right-1/2 -mx-[50vw] flex w-screen scroll-mt-4 flex-col gap-6 px-2 sm:px-6">
    <div class="flex items-center justify-between gap-4 px-1 pt-2 sm:px-0">
        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <h1 class="truncate text-lg font-bold tracking-[0.15em] text-zinc-800 uppercase sm:text-xl dark:text-zinc-50">
                {{ $gallery->title }}
            </h1>
            <p
                class="truncate text-[11px] tracking-[0.25em] text-zinc-500 uppercase"
                title="{{ collect([$gallery->created_at->translatedFormat('d M Y'), $gallery->location, $gallery->available_until ? __('Disponible hasta :date', ['date' => $gallery->available_until->translatedFormat('d M Y')]) : null])->filter()->implode(' · ') }}"
            >
                {{ $gallery->photographer->company_name ?: $gallery->photographer->name }}
            </p>
        </div>

        <div
            wire:key="gallery-actions-{{ $activeAlbumId }}"
            x-data="{
                open: false,
                photos: @js($this->activeAlbumMedia->filter->isPhoto()->values()->map(fn ($media) => route('media.show', $media))->all()),
                index: 0,
                visible: true,
                interval: null,
                copied: false,
                start() {
                    if (! this.photos.length) return;
                    this.open = true;
                    this.index = 0;
                    this.visible = true;
                    this.restartAutoplay();
                },
                advance(direction) {
                    this.visible = false;
                    setTimeout(() => {
                        this.index = (this.index + direction + this.photos.length) % this.photos.length;
                        this.visible = true;
                    }, 400);
                },
                manualAdvance(direction) {
                    this.advance(direction);
                    this.restartAutoplay();
                },
                restartAutoplay() {
                    clearInterval(this.interval);
                    this.interval = setInterval(() => this.advance(1), 3500);
                },
                stop() {
                    this.open = false;
                    clearInterval(this.interval);
                },
                share() {
                    const url = @js(route('galleries.show', $gallery));
                    const title = @js($gallery->title);

                    if (navigator.share) {
                        navigator.share({ title, url }).catch(() => {});
                        return;
                    }

                    navigator.clipboard.writeText(url);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                },
            }"
            class="flex shrink-0 items-center gap-1"
        >
            <button
                type="button"
                x-on:click="share()"
                class="flex size-11 cursor-pointer items-center justify-center rounded-full text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                aria-label="{{ __('Compartir') }}"
                title="{{ __('Compartir') }}"
            >
                <flux:icon x-show="!copied" name="share" class="size-5" />
                <flux:icon x-show="copied" x-cloak name="check" class="size-5" />
            </button>

            @if ($this->activeAlbumMedia->contains->isPhoto())
                <button
                    type="button"
                    x-on:click="start()"
                    class="flex size-11 cursor-pointer items-center justify-center rounded-full text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                    aria-label="{{ __('Presentación') }}"
                    title="{{ __('Presentación') }}"
                >
                    <flux:icon name="play" variant="outline" class="size-5" />
                </button>
            @endif

            <div
                x-show="open"
                x-cloak
                x-on:keydown.escape.window="stop()"
                x-on:keydown.arrow-left.window="open && manualAdvance(-1)"
                x-on:keydown.arrow-right.window="open && manualAdvance(1)"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black"
            >
                <button
                    type="button"
                    x-on:click="stop()"
                    class="absolute top-6 right-6 flex size-11 cursor-pointer items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
                    aria-label="{{ __('Cerrar') }}"
                >
                    <flux:icon name="x-mark" class="size-6" />
                </button>

                <button
                    type="button"
                    x-show="photos.length > 1"
                    x-on:click="manualAdvance(-1)"
                    class="absolute top-1/2 left-3 flex size-11 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20 sm:left-6"
                    aria-label="{{ __('Anterior') }}"
                >
                    <flux:icon name="chevron-left" class="size-6" />
                </button>

                <button
                    type="button"
                    x-show="photos.length > 1"
                    x-on:click="manualAdvance(1)"
                    class="absolute top-1/2 right-3 flex size-11 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20 sm:right-6"
                    aria-label="{{ __('Siguiente') }}"
                >
                    <flux:icon name="chevron-right" class="size-6" />
                </button>

                <img
                    x-show="open"
                    :src="photos[index]"
                    x-bind:class="visible ? 'opacity-100' : 'opacity-0'"
                    class="max-h-screen max-w-screen object-contain transition-opacity duration-700"
                    alt=""
                >
            </div>
        </div>
    </div>

    @unless ($this->isUnlocked)
        <div
            x-data="{ open: false }"
            x-on:open-unlock-modal.window="open = true; $nextTick(() => $refs.codeInput.focus())"
        >
            <div class="fixed right-6 bottom-6 z-40">
                <flux:button
                    type="button"
                    x-on:click="open = true; $nextTick(() => $refs.codeInput.focus())"
                    variant="primary"
                    icon="lock-closed"
                    class="shadow-lg"
                >
                    {{ __('Tengo un código') }}
                </flux:button>
            </div>

            <div
                x-show="open"
                x-cloak
                x-transition.opacity
                x-on:keydown.escape.window="open = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-6"
            >
                <div
                    x-on:click.outside="open = false"
                    x-transition
                    class="flex w-full max-w-sm flex-col items-center gap-4 rounded-2xl border border-[#e2d6d0] bg-[#f8f3f0] p-8 text-center shadow-[0_4px_16px_-4px_rgba(61,56,53,0.35)] dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <flux:heading>{{ __('¿Quieres ver el resto de las fotos?') }}</flux:heading>
                    <flux:text class="text-zinc-500">
                        {{ __('Ingresa el código que te compartió el fotógrafo. No necesitas cuenta para verlas ni descargarlas.') }}
                    </flux:text>

                    <form wire:submit="unlock" class="flex w-full flex-col gap-3">
                        <flux:input x-ref="codeInput" wire:model="code" :placeholder="__('Código de acceso')" />
                        <flux:button type="submit" variant="primary">{{ __('Desbloquear') }}</flux:button>
                    </form>

                    @error('code')
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror

                    <flux:text class="text-xs text-zinc-400">
                        {{ __('¿Quieres guardar esta galería en tu cuenta para volver a ella después?') }}
                        <flux:link :href="route('register')" wire:navigate>{{ __('Regístrate') }}</flux:link>
                    </flux:text>
                </div>
            </div>
        </div>
    @endunless

    @if ($this->albums->count() > 1)
        <div class="flex gap-6 overflow-x-auto border-b border-zinc-200 px-1 [scrollbar-width:none] sm:px-0 dark:border-zinc-700">
            @foreach ($this->albums as $album)
                <button
                    type="button"
                    wire:key="album-tab-{{ $album->id }}"
                    wire:click="selectAlbum({{ $album->id }})"
                    class="shrink-0 cursor-pointer border-b-2 pb-3 text-xs font-medium tracking-[0.15em] uppercase transition-colors {{ $album->id === $activeAlbumId ? 'border-zinc-800 text-zinc-800 dark:border-zinc-50 dark:text-zinc-50' : 'border-transparent text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300' }}"
                >
                    {{ $album->title }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($this->activeAlbumMedia->isEmpty())
        <flux:text class="text-center text-zinc-500">{{ __('No hay fotos disponibles en este álbum todavía.') }}</flux:text>
    @else
        {{-- Masonry: each photo keeps its own proportions; columns fill top-to-bottom. --}}
        <div class="columns-2 gap-2 sm:columns-3 lg:columns-4">
            @foreach ($this->activeAlbumMedia as $media)
                @php
                    $isTeaser = ! $this->isUnlocked && ! $media->is_featured;
                    $isSelected = in_array($media->id, $selected, true);
                @endphp
                <div
                    wire:key="media-{{ $media->id }}"
                    class="group relative mb-2 break-inside-avoid overflow-hidden bg-zinc-100 dark:bg-zinc-800"
                >
                    @if ($isSelected)
                        <div class="pointer-events-none absolute inset-0 z-10 ring-4 ring-[#B3907A] ring-inset"></div>
                    @endif

                    <button
                        type="button"
                        @if ($isTeaser)
                            x-on:click="$dispatch('open-unlock-modal')"
                        @else
                            wire:click="openLightbox({{ $media->id }})"
                        @endif
                        class="block w-full cursor-zoom-in"
                        aria-label="{{ $isTeaser ? __('Desbloquear para ver') : __('Ver foto') }}"
                    >
                        @if ($media->isVideo())
                            <div class="relative flex aspect-video items-center justify-center bg-zinc-800">
                                <video
                                    src="{{ route('media.show', $media) }}#t=0.1"
                                    preload="metadata"
                                    muted
                                    playsinline
                                    class="absolute inset-0 h-full w-full object-cover"
                                ></video>
                                <flux:icon name="play-circle" class="relative size-14 text-white drop-shadow" />
                            </div>
                        @else
                            <img
                                src="{{ route('media.show', $media) }}"
                                alt=""
                                loading="lazy"
                                x-data="{ loaded: false }"
                                x-init="loaded = $el.complete"
                                x-on:load="loaded = true"
                                x-bind:class="loaded ? 'opacity-100' : 'opacity-0'"
                                class="block h-auto w-full transition duration-500 {{ $isTeaser ? 'scale-110 blur-lg' : '' }}"
                            >
                        @endif

                        @if ($isTeaser)
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/45 p-4 text-center">
                                <flux:icon name="lock-closed" class="size-6 text-white" />
                                <flux:text class="text-sm font-medium text-white">
                                    {{ __('¿Quieres ver las demás fotos?') }}
                                </flux:text>
                                <flux:text class="text-xs text-white/80">
                                    {{ __('Ingresa el código de desbloqueo') }}
                                </flux:text>
                            </div>
                        @endif
                    </button>

                    @if ($this->isUnlocked)
                        {{-- Hover bar: bottom gradient with the photo's actions (always visible on touch or when selected). --}}
                        <div @class([
                            'pointer-events-none absolute inset-x-0 bottom-0 flex items-end justify-end gap-1 bg-linear-to-t from-black/55 to-transparent px-2 pt-12 pb-2 transition-opacity',
                            'opacity-100' => $isSelected,
                            'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 pointer-coarse:opacity-100' => ! $isSelected,
                        ])>
                            <label
                                class="pointer-events-auto flex size-9 cursor-pointer items-center justify-center rounded-full text-white transition-colors hover:bg-white/15"
                                title="{{ $isSelected ? __('Quitar de la selección') : __('Seleccionar') }}"
                            >
                                <input
                                    type="checkbox"
                                    class="peer sr-only"
                                    wire:click="toggleSelected({{ $media->id }})"
                                    @checked($isSelected)
                                >
                                <span class="sr-only">{{ __('Seleccionar') }}</span>
                                <flux:icon name="check-circle" :variant="$isSelected ? 'solid' : 'outline'" class="size-6" />
                            </label>

                            <a
                                href="{{ route('media.download', $media) }}"
                                class="pointer-events-auto flex size-9 items-center justify-center rounded-full text-white transition-colors hover:bg-white/15"
                                title="{{ __('Descargar') }}"
                            >
                                <flux:icon name="arrow-down-tray" class="size-6" />
                                <span class="sr-only">{{ __('Descargar') }}</span>
                            </a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
    </div>

    @if ($this->isUnlocked && $this->activeAlbumMedia->isNotEmpty())
        {{-- Selection bar: full-width single row on phones, floating pill from sm up. --}}
        <div class="fixed inset-x-0 bottom-0 z-10 flex items-center gap-2 border-t border-[#e2d6d0] bg-[#f8f3f0]/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-4px_16px_-8px_rgba(61,56,53,0.35)] backdrop-blur-sm sm:bottom-6 sm:mx-auto sm:w-fit sm:max-w-[calc(100%-2rem)] sm:gap-4 sm:rounded-full sm:border sm:px-6 sm:py-3 sm:shadow-[0_4px_16px_-4px_rgba(61,56,53,0.35)] dark:border-zinc-700 dark:bg-zinc-900/95">
            <div class="flex min-w-0 flex-1 flex-col leading-tight sm:flex-none">
                <flux:text class="truncate text-sm sm:hidden">
                    {{ trans_choice(':count seleccionada|:count seleccionadas', count($selected), ['count' => count($selected)]) }}
                </flux:text>
                <flux:text class="text-xs text-zinc-400 sm:hidden">
                    {{ number_format($this->selectedTotalBytes / 1_000_000, 1) }} MB
                </flux:text>
                <flux:text class="hidden whitespace-nowrap sm:block">
                    {{ trans_choice(':count elemento seleccionado|:count elementos seleccionados', count($selected), ['count' => count($selected)]) }}
                    · {{ number_format($this->selectedTotalBytes / 1_000_000, 1) }} MB
                </flux:text>
            </div>

            <flux:button size="sm" variant="ghost" wire:click="selectAllInActiveAlbum" class="shrink-0">
                <span class="sm:hidden">{{ __('Todo') }}</span>
                <span class="hidden sm:inline">{{ __('Seleccionar todo') }}</span>
            </flux:button>

            <form
                method="POST"
                action="{{ route('galleries.download-selection', $gallery) }}"
                x-data="{ downloading: false }"
                x-on:submit="downloading = true; setTimeout(() => downloading = false, 6000)"
                class="shrink-0"
            >
                @csrf
                @foreach ($selected as $mediaId)
                    <input type="hidden" name="media_ids[]" value="{{ $mediaId }}">
                @endforeach
                <flux:button
                    size="sm"
                    type="submit"
                    variant="primary"
                    :loading="false"
                    x-bind:disabled="downloading || @js(empty($selected))"
                >
                    <span x-show="!downloading" x-cloak>
                        <span class="sm:hidden">{{ __('Descargar') }}</span>
                        <span class="hidden sm:inline">{{ __('Descargar (.zip)') }}</span>
                    </span>
                    <span x-show="downloading" x-cloak>{{ __('Preparando…') }}</span>
                </flux:button>
            </form>
        </div>
    @endif

    <flux:modal name="lightbox" class="w-[80vw]! max-w-4xl!" @close="closeLightbox">
        @if ($this->lightboxMedia)
            <div class="flex w-full flex-col gap-3">
                @if ($this->lightboxMedia->isVideo())
                    <video controls autoplay preload="metadata" class="max-h-[75vh] w-full rounded-lg bg-black" src="{{ route('media.show', $this->lightboxMedia) }}"></video>
                @else
                    <img src="{{ route('media.show', $this->lightboxMedia) }}" class="max-h-[75vh] w-full rounded-lg object-contain" alt="">
                @endif

                <div class="flex items-center justify-between">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="chevron-left"
                        wire:click="lightboxPrevious"
                        :disabled="$this->lightboxIndex === 0"
                    >
                        {{ __('Anterior') }}
                    </flux:button>

                    @if ($this->isUnlocked)
                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('media.download', $this->lightboxMedia)">
                            {{ __('Descargar') }}
                        </flux:button>
                    @endif

                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="chevron-right"
                        wire:click="lightboxNext"
                        :disabled="$this->lightboxIndex === $this->activeAlbumMedia->count() - 1"
                    >
                        {{ __('Siguiente') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
