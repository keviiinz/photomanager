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
        $this->gallery = $gallery;
        $this->activeAlbumId = $gallery->albums()->orderBy('position')->first()?->id;
    }

    #[Computed]
    public function isUnlocked(): bool
    {
        return $this->gallery->isUnlockedFor(Auth::user());
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

        return $this->isUnlocked ? $media : $media->where('is_featured', true)->values();
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
        if (! Auth::check()) {
            return;
        }

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

<div class="mx-auto flex max-w-5xl flex-col gap-8 pb-24">
    <div>
        <flux:heading size="xl">{{ $gallery->title }}</flux:heading>
        <flux:text class="text-zinc-500">
            {{ __('Por :photographer', ['photographer' => $gallery->photographer->name]) }}
            · {{ $gallery->created_at->translatedFormat('d M Y') }}
            @if ($gallery->location)
                · {{ $gallery->location }}
            @endif
        </flux:text>
        @if ($gallery->available_until)
            <flux:text class="text-sm text-zinc-500">
                {{ __('Disponible hasta :date', ['date' => $gallery->available_until->translatedFormat('d M Y')]) }}
            </flux:text>
        @endif
    </div>

    @unless ($this->isUnlocked)
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading class="mb-2">{{ __('¿Quieres ver el resto de las fotos?') }}</flux:heading>

            @guest
                <flux:text class="mb-4 text-zinc-500">
                    {{ __('Inicia sesión o regístrate como cliente y vuelve a esta página para ingresar tu código.') }}
                </flux:text>
                <div class="flex gap-2">
                    <flux:button :href="route('login')" wire:navigate>{{ __('Iniciar sesión') }}</flux:button>
                    <flux:button :href="route('register')" variant="primary" wire:navigate>{{ __('Registrarme') }}</flux:button>
                </div>
            @else
                <form wire:submit="unlock" class="flex max-w-sm gap-2">
                    <flux:input wire:model="code" :placeholder="__('Código de acceso')" />
                    <flux:button type="submit" variant="primary">{{ __('Desbloquear') }}</flux:button>
                </form>
                @error('code')
                    <flux:text class="mt-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror
            @endguest
        </div>
    @endunless

    @if ($this->albums->count() > 1)
        <div class="flex flex-wrap gap-2">
            @foreach ($this->albums as $album)
                <flux:button
                    size="sm"
                    :variant="$album->id === $activeAlbumId ? 'primary' : 'ghost'"
                    wire:click="selectAlbum({{ $album->id }})"
                >
                    {{ $album->title }}
                </flux:button>
            @endforeach
        </div>
    @endif

    @if ($this->activeAlbumMedia->isEmpty())
        <flux:text class="text-zinc-500">{{ __('No hay fotos disponibles en este álbum todavía.') }}</flux:text>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($this->activeAlbumMedia as $media)
                <div class="relative overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <button type="button" wire:click="openLightbox({{ $media->id }})" class="block w-full cursor-pointer">
                        @if ($media->isVideo())
                            <div class="flex aspect-square items-center justify-center bg-zinc-800">
                                <flux:icon name="play-circle" class="size-10 text-white" />
                            </div>
                        @else
                            <img src="{{ route('media.show', $media) }}" class="aspect-square w-full object-cover" alt="">
                        @endif
                    </button>

                    @if ($this->isUnlocked)
                        <label class="absolute top-2 left-2 flex items-center gap-1 rounded bg-black/60 px-2 py-1">
                            <input
                                type="checkbox"
                                wire:click="toggleSelected({{ $media->id }})"
                                @checked(in_array($media->id, $selected, true))
                            >
                            <span class="text-xs text-white">{{ __('Seleccionar') }}</span>
                        </label>

                        <a href="{{ route('media.download', $media) }}" class="absolute top-2 right-2 rounded bg-black/60 p-1.5 text-white">
                            <flux:icon name="arrow-down-tray" class="size-4" />
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($this->isUnlocked && $this->activeAlbumMedia->isNotEmpty())
        <div class="fixed bottom-0 left-0 right-0 flex items-center justify-between gap-4 border-t border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>
                {{ trans_choice(':count elemento seleccionado|:count elementos seleccionados', count($selected), ['count' => count($selected)]) }}
                · {{ number_format($this->selectedTotalBytes / 1_000_000, 1) }} MB
            </flux:text>

            <div class="flex gap-2">
                <flux:button variant="ghost" wire:click="selectAllInActiveAlbum">{{ __('Seleccionar todo el álbum') }}</flux:button>

                <form method="POST" action="{{ route('galleries.download-selection', $gallery) }}">
                    @csrf
                    @foreach ($selected as $mediaId)
                        <input type="hidden" name="media_ids[]" value="{{ $mediaId }}">
                    @endforeach
                    <flux:button type="submit" variant="primary" :disabled="empty($selected)">
                        {{ __('Descargar selección (.zip)') }}
                    </flux:button>
                </form>
            </div>
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
