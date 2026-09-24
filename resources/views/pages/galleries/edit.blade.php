<?php

use App\Concerns\GalleryValidationRules;
use App\Enums\CoverLayout;
use App\Enums\GalleryStatus;
use App\Enums\MediaType;
use App\Models\Album;
use App\Models\Gallery;
use App\Models\Media;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Editar galería'), Layout('layouts::editor')] class extends Component {
    use GalleryValidationRules, WithFileUploads;

    public Gallery $gallery;

    public string $title = '';
    public string $client_name = '';
    public ?string $location = null;
    public ?string $available_until = null;

    public ?int $activeAlbumId = null;
    public string $newAlbumTitle = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newFiles = [];

    /**
     * The unlock code in plain text, shown once right after it's (re)generated.
     * It is never persisted anywhere except the hash in the database.
     */
    public ?string $revealedCode = null;

    public function mount(Gallery $gallery): void
    {
        $this->authorize('update', $gallery);

        $this->gallery = $gallery;
        $this->title = $gallery->title;
        $this->client_name = $gallery->client_name;
        $this->location = $gallery->location;
        $this->available_until = $gallery->available_until?->format('Y-m-d');
        $this->activeAlbumId = $gallery->albums()->orderBy('position')->first()?->id;
        $this->revealedCode = session()->pull('revealed_code');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Album>
     */
    #[Computed]
    public function albums()
    {
        return $this->gallery->albums()->withCount('media')->get();
    }

    #[Computed]
    public function activeAlbum(): ?Album
    {
        return $this->albums->firstWhere('id', $this->activeAlbumId);
    }

    #[Computed]
    public function coverImage(): ?Media
    {
        return $this->gallery->coverImage();
    }

    #[Computed]
    public function coverImageId(): ?int
    {
        return $this->coverImage?->id;
    }

    /**
     * Every photo in the gallery, across albums, for the cover picker.
     *
     * @return \Illuminate\Support\Collection<int, Media>
     */
    #[Computed]
    public function allPhotos()
    {
        return $this->gallery->media()->where('type', MediaType::Photo)->orderBy('albums.position')->orderBy('media.position')->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Media>
     */
    #[Computed]
    public function activeAlbumMedia()
    {
        return $this->activeAlbum?->media()->get() ?? collect();
    }

    public function selectAlbum(int $albumId): void
    {
        $this->activeAlbumId = $albumId;
    }

    public function addAlbum(): void
    {
        $this->validate(['newAlbumTitle' => ['required', 'string', 'max:255']]);

        $position = ((int) $this->gallery->albums()->max('position')) + 1;

        $album = $this->gallery->albums()->create([
            'title' => $this->newAlbumTitle,
            'position' => $position,
        ]);

        $this->newAlbumTitle = '';
        $this->activeAlbumId = $album->id;
        unset($this->albums);

        Flux::toast(variant: 'success', text: __('Álbum creado.'));
    }

    public function deleteAlbum(int $albumId): void
    {
        abort_if($this->gallery->albums()->count() <= 1, 422, __('La galería debe tener al menos un álbum.'));

        $album = $this->gallery->albums()->findOrFail($albumId);

        foreach ($album->media as $media) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $album->delete();

        if ($this->activeAlbumId === $albumId) {
            $this->activeAlbumId = $this->gallery->albums()->orderBy('position')->first()?->id;
        }

        unset($this->albums, $this->coverImage, $this->coverImageId, $this->allPhotos);

        Flux::toast(variant: 'success', text: __('Álbum eliminado.'));
    }

    public function renameAlbum(int $albumId, string $title): void
    {
        $title = trim($title);

        // Keyed as "albumTitle" so an error doesn't show up under the gallery's own "title" field.
        validator(['albumTitle' => $title], ['albumTitle' => ['required', 'string', 'max:255']], attributes: ['albumTitle' => __('nombre del set')])->validate();

        $this->gallery->albums()->findOrFail($albumId)->update(['title' => $title]);

        unset($this->albums);
    }

    /**
     * Persist the set order after a drag-and-drop. Ids that don't belong to this gallery are ignored.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function saveAlbumOrder(array $orderedIds): void
    {
        $validIds = $this->gallery->albums()->pluck('id')->all();
        $orderedIds = array_values(array_filter($orderedIds, fn ($id) => in_array($id, $validIds, true)));

        foreach ($orderedIds as $position => $albumId) {
            Album::whereKey($albumId)->update(['position' => $position]);
        }

        unset($this->albums);
    }

    /**
     * Move a set one place up (-1) or down (+1), for touch screens where dragging isn't available.
     */
    public function moveAlbum(int $albumId, int $direction): void
    {
        $ids = $this->albums->pluck('id')->values()->all();
        $index = array_search($albumId, $ids, true);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || $target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $this->saveAlbumOrder($ids);
    }

    public function removePendingFile(int $index): void
    {
        unset($this->newFiles[$index]);
        $this->newFiles = array_values($this->newFiles);
    }

    public function uploadFiles(): void
    {
        $this->validate([
            'newFiles' => ['required', 'array', 'min:1'],
            'newFiles.*' => ['file', 'max:102400', 'mimes:jpg,jpeg,png,webp,mp4,mov'],
        ]);

        $album = $this->activeAlbum;

        abort_unless($album, 422);

        $disk = config('filesystems.default');
        $position = ((int) $album->media()->max('position')) + 1;

        foreach ($this->newFiles as $file) {
            $path = $file->store("galleries/{$this->gallery->id}/{$album->id}", $disk);
            $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');

            $album->media()->create([
                'type' => $isVideo ? MediaType::Video : MediaType::Photo,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'position' => $position++,
            ]);
        }

        // The editor shows its own upload status panel, so no toast here.
        $this->newFiles = [];
        unset($this->albums, $this->coverImage, $this->coverImageId, $this->allPhotos);
    }

    public function toggleFeatured(int $mediaId): void
    {
        $media = $this->findMedia($mediaId);

        $media->update(['is_featured' => ! $media->is_featured]);

        unset($this->albums, $this->coverImage, $this->coverImageId, $this->allPhotos);
    }

    public function setCover(int $mediaId): void
    {
        $media = $this->findMedia($mediaId);

        abort_unless($media->isPhoto(), 422);

        $this->gallery->update(['cover_media_id' => $media->id]);

        unset($this->coverImage, $this->coverImageId);

        Flux::toast(variant: 'success', text: __('Portada actualizada.'));
    }

    public function setCoverLayout(string $layout): void
    {
        $this->gallery->update(['cover_layout' => CoverLayout::from($layout)]);
    }

    public function toggleCoverInGallery(): void
    {
        $this->gallery->update(['show_cover_in_gallery' => ! $this->gallery->show_cover_in_gallery]);
    }

    public function setStatus(string $status): void
    {
        $status = GalleryStatus::from($status);

        if ($this->gallery->status === $status) {
            return;
        }

        $this->gallery->update(['status' => $status]);

        Flux::toast(
            variant: 'success',
            text: $status === GalleryStatus::Published
                ? __('Galería publicada. Ya la puede ver quien tenga el enlace.')
                : __('La galería volvió a borrador. Solo tú puedes verla.'),
        );
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function saveMediaOrder(array $orderedIds): void
    {
        $album = $this->activeAlbum;

        abort_unless($album, 422);

        $validIds = $album->media()->pluck('id')->all();

        foreach ($orderedIds as $index => $mediaId) {
            if (! in_array($mediaId, $validIds, true)) {
                continue;
            }

            Media::whereKey($mediaId)->update(['position' => $index]);
        }

        unset($this->albums);

        Flux::toast(variant: 'success', text: __('Orden guardado.'));
    }

    public function deleteMedia(int $mediaId): void
    {
        $media = $this->findMedia($mediaId);

        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        unset($this->albums, $this->coverImage, $this->coverImageId, $this->allPhotos);

        Flux::toast(variant: 'success', text: __('Archivo eliminado.'));
    }

    protected function findMedia(int $mediaId): Media
    {
        return Media::whereHas('album', fn ($query) => $query->where('gallery_id', $this->gallery->id))
            ->findOrFail($mediaId);
    }

    public function saveDetails(): void
    {
        $validated = $this->validate([
            'title' => $this->titleRules(),
            'client_name' => $this->clientNameRules(),
            'location' => $this->locationRules(),
            'available_until' => $this->availableUntilRules(),
        ]);

        $this->gallery->update([
            ...$validated,
            // An emptied date input arrives as '' — store "no expiry" as NULL.
            'available_until' => $validated['available_until'] ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('Galería actualizada.'));
    }

    public function regenerateCode(): void
    {
        $code = Str::upper(Str::random(8));

        $this->gallery->update(['unlock_code' => $code]);

        activity('gallery')
            ->causedBy(auth()->user())
            ->performedOn($this->gallery)
            ->event('code_regenerated')
            ->log("Regeneró el código de desbloqueo de la galería \"{$this->gallery->title}\"");

        $this->revealedCode = $code;

        Flux::modal('share')->show();
    }

    public function dismissRevealedCode(): void
    {
        $this->revealedCode = null;
    }

    public function deleteGallery(): void
    {
        $this->authorize('delete', $this->gallery);

        foreach ($this->gallery->media as $media) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $this->gallery->delete();

        $this->redirect(route('galleries.index'), navigate: true);
    }
}; ?>

@php
    $expired = $gallery->isExpired();
    $published = $gallery->isPublished();
    $rust = 'bg-[#B3907A] text-white hover:bg-[#9f7d68]';
@endphp

<div
    x-data="{
        sidebarOpen: window.matchMedia('(min-width: 1024px)').matches,
        tab: 'photos',
        search: '',
        closeSidebarOnMobile() {
            if (! window.matchMedia('(min-width: 1024px)').matches) this.sidebarOpen = false;
        },
        togglePanel(tab) {
            if (this.sidebarOpen && this.tab === tab) {
                this.sidebarOpen = false;

                return;
            }

            this.tab = tab;
            this.sidebarOpen = true;
        },
    }"
    @if ($revealedCode) x-init="$nextTick(() => $flux.modal('share').show())" @endif
    class="flex h-dvh flex-col"
>
    {{-- ───────────── Top bar ───────────── --}}
    <header class="flex h-16 shrink-0 items-center gap-3 border-b border-zinc-200 px-3 sm:gap-5 sm:px-5 dark:border-zinc-700">
        <a
            href="{{ route('galleries.index') }}"
            wire:navigate
            class="flex size-9 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
            aria-label="{{ __('Volver a galerías') }}"
        >
            <flux:icon name="chevron-left" class="size-5" />
        </a>

        <div class="flex min-w-0 items-center gap-2.5">
            <span
                @class([
                    'size-2 shrink-0 rounded-full lg:hidden',
                    'border border-[#918674]' => ! $published,
                    'bg-[#2f6b3a]' => $published && ! $expired,
                    'bg-zinc-400' => $published && $expired,
                ])
                title="{{ ! $published ? __('Borrador') : ($expired ? __('Expirada') : __('Publicada')) }}"
            ></span>
            <div class="min-w-0">
                <p class="truncate text-lg leading-tight font-medium">{{ $gallery->title }}</p>
                <p class="hidden text-sm leading-tight text-zinc-500 lg:block">{{ $gallery->created_at->translatedFormat('j M Y') }}</p>
            </div>
        </div>

        {{-- Status: draft ↔ published. Published galleries past their date read "Expirada". --}}
        <flux:dropdown position="bottom" align="start" class="hidden lg:block">
            <button
                type="button"
                data-test="gallery-status"
                @class([
                    'inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold tracking-wider uppercase transition-colors',
                    'bg-[#E1DACA] text-[#6d6058] hover:bg-[#d6cdb9]' => ! $published,
                    'bg-[#2f6b3a]/10 text-[#2f6b3a] hover:bg-[#2f6b3a]/15' => $published && ! $expired,
                    'bg-zinc-100 text-zinc-500 hover:bg-zinc-200 dark:bg-zinc-800' => $published && $expired,
                ])
            >
                <span @class([
                    'size-1.5 rounded-full',
                    'border border-[#918674]' => ! $published,
                    'bg-[#2f6b3a]' => $published && ! $expired,
                    'bg-zinc-400' => $published && $expired,
                ])></span>
                {{ ! $published ? __('Borrador') : ($expired ? __('Expirada') : __('Publicada')) }}
                <flux:icon name="chevron-down" variant="micro" class="size-3.5" />
            </button>

            <flux:menu class="w-72">
                <flux:menu.item wire:click="setStatus('draft')" icon="pencil-square">
                    <div class="flex flex-col py-0.5">
                        <span class="font-medium">{{ __('Borrador') }}{{ ! $published ? ' ✓' : '' }}</span>
                        <span class="text-xs text-zinc-500">{{ __('Solo tú puedes verla mientras la preparas.') }}</span>
                    </div>
                </flux:menu.item>
                <flux:menu.item wire:click="setStatus('published')" icon="globe-alt">
                    <div class="flex flex-col py-0.5">
                        <span class="font-medium">{{ __('Publicada') }}{{ $published ? ' ✓' : '' }}</span>
                        <span class="text-xs text-zinc-500">{{ __('Cualquiera con el enlace puede verla.') }}</span>
                    </div>
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>

        <div class="relative ms-2 hidden w-full max-w-md md:block">
            <flux:icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-zinc-400" />
            <label for="media-search" class="sr-only">{{ __('Buscar archivos') }}</label>
            <input
                id="media-search"
                type="search"
                x-model="search"
                placeholder="{{ __('Buscar archivos') }}"
                class="h-10 w-full rounded-md bg-zinc-100 ps-10 pe-3 text-sm placeholder:text-zinc-400 focus:bg-white focus:ring-2 focus:ring-[#B3907A]/40 focus:outline-none dark:bg-zinc-800"
            >
        </div>

        {{-- Desktop actions --}}
        <div class="ms-auto hidden shrink-0 items-center gap-3 lg:flex">
            <flux:dropdown position="bottom" align="end">
                <flux:button variant="ghost" icon:trailing="chevron-down">{{ __('Más') }}</flux:button>

                <flux:menu>
                    <flux:menu.item icon="key" wire:click="regenerateCode" wire:confirm="{{ __('¿Regenerar el código? El código anterior dejará de funcionar.') }}">
                        {{ __('Regenerar código') }}
                    </flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item icon="trash" variant="danger" wire:click="deleteGallery" wire:confirm="{{ __('¿Eliminar esta galería de forma permanente? Se borrarán sus sets y archivos.') }}">
                        {{ __('Eliminar galería') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            <a
                href="{{ route('galleries.show', $gallery) }}"
                target="_blank"
                class="rounded-md px-3 py-2 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
                {{ __('Vista previa') }}
            </a>

            <button
                type="button"
                x-on:click="$flux.modal('share').show()"
                class="flex h-10 cursor-pointer items-center rounded-md px-6 text-sm font-semibold transition-colors {{ $rust }}"
            >
                {{ __('Compartir') }}
            </button>
        </div>

        {{-- Mobile / tablet actions: more menu, details (gear) and panel (hamburger) --}}
        <div class="ms-auto flex shrink-0 items-center gap-0.5 lg:hidden">
            <flux:dropdown position="bottom" align="end">
                <button type="button" class="flex size-10 cursor-pointer items-center justify-center rounded-lg text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="{{ __('Más opciones') }}" data-test="mobile-more">
                    <flux:icon name="ellipsis-horizontal" class="size-6" />
                </button>

                <flux:menu class="w-64">
                    <flux:menu.group :heading="__('Estado')">
                        <flux:menu.item wire:click="setStatus('draft')" icon="pencil-square">
                            {{ __('Borrador') }}{{ ! $published ? ' ✓' : '' }}
                        </flux:menu.item>
                        <flux:menu.item wire:click="setStatus('published')" icon="globe-alt">
                            {{ __('Publicada') }}{{ $published ? ' ✓' : '' }}
                        </flux:menu.item>
                    </flux:menu.group>
                    <flux:menu.separator />
                    <flux:menu.item icon="share" x-on:click="$flux.modal('share').show()">{{ __('Compartir') }}</flux:menu.item>
                    <flux:menu.item icon="eye" :href="route('galleries.show', $gallery)" target="_blank">{{ __('Vista previa') }}</flux:menu.item>
                    <flux:menu.item icon="key" wire:click="regenerateCode" wire:confirm="{{ __('¿Regenerar el código? El código anterior dejará de funcionar.') }}">
                        {{ __('Regenerar código') }}
                    </flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item icon="trash" variant="danger" wire:click="deleteGallery" wire:confirm="{{ __('¿Eliminar esta galería de forma permanente? Se borrarán sus sets y archivos.') }}">
                        {{ __('Eliminar galería') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            <button
                type="button"
                x-on:click="togglePanel('settings')"
                class="flex size-10 cursor-pointer items-center justify-center rounded-lg text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                x-bind:class="sidebarOpen && tab === 'settings' && 'bg-zinc-100 !text-zinc-900 dark:bg-zinc-800 dark:!text-white'"
                aria-label="{{ __('Detalles de la galería') }}"
                data-test="mobile-details"
            >
                <flux:icon name="cog-6-tooth" class="size-6" />
            </button>

            <button
                type="button"
                x-on:click="sidebarOpen ? (sidebarOpen = false) : togglePanel(tab === 'settings' ? 'photos' : tab)"
                class="flex size-10 cursor-pointer items-center justify-center rounded-lg text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                x-bind:aria-label="sidebarOpen ? @js(__('Cerrar panel')) : @js(__('Abrir panel'))"
                x-bind:aria-expanded="sidebarOpen"
                data-test="mobile-panel"
            >
                <flux:icon name="bars-3" class="size-6" x-show="! sidebarOpen" />
                <flux:icon name="x-mark" class="size-6" x-show="sidebarOpen" x-cloak />
            </button>
        </div>
    </header>

    <div class="relative flex min-h-0 flex-1">
        {{-- ───────────── Sidebar ───────────── --}}
        <aside
            x-show="sidebarOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-3 lg:opacity-100 lg:translate-y-0 lg:-translate-x-full"
            x-transition:enter-end="opacity-100 translate-y-0 lg:translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 lg:translate-x-0"
            x-transition:leave-end="opacity-0 -translate-y-3 lg:opacity-100 lg:translate-y-0 lg:-translate-x-full"
            x-on:keydown.escape.window="closeSidebarOnMobile()"
            data-test="editor-panel"
            class="absolute inset-0 z-30 flex w-full shrink-0 flex-col bg-white shadow-lg lg:static lg:z-auto lg:w-80 lg:border-e lg:border-zinc-200 lg:shadow-none dark:bg-zinc-900 dark:lg:border-zinc-700"
        >
            {{-- Cover --}}
            <div class="group relative hidden h-52 shrink-0 bg-zinc-100 lg:block dark:bg-zinc-800">
                @if ($this->coverImage)
                    <img src="{{ route('media.show', $this->coverImage) }}" alt="{{ __('Portada') }}" class="size-full object-cover">
                @else
                    <div class="flex size-full flex-col items-center justify-center gap-2 text-zinc-400">
                        <flux:icon name="photo" class="size-8" />
                        <span class="text-sm">{{ __('Sin portada') }}</span>
                    </div>
                @endif

                @if ($this->allPhotos->isNotEmpty())
                    <button
                        type="button"
                        x-on:click="$flux.modal('cover-picker').show()"
                        class="absolute inset-0 flex cursor-pointer items-center justify-center bg-black/0 text-sm font-medium text-white opacity-0 transition group-hover:bg-black/35 group-hover:opacity-100 focus-visible:bg-black/35 focus-visible:opacity-100"
                    >
                        <span class="flex items-center gap-2 rounded-full bg-black/40 px-4 py-2 backdrop-blur-sm">
                            <flux:icon name="photo" variant="micro" class="size-4" />
                            {{ __('Cambiar portada') }}
                        </span>
                    </button>
                @endif
            </div>

            {{-- Tabs --}}
            <div class="grid shrink-0 grid-cols-3 border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50" role="tablist">
                @foreach (['photos' => ['photo', __('Fotos')], 'design' => ['paint-brush', __('Diseño')], 'settings' => ['cog-6-tooth', __('Ajustes')]] as $key => [$icon, $label])
                    <button
                        type="button"
                        role="tab"
                        x-on:click="tab = '{{ $key }}'"
                        x-bind:aria-selected="tab === '{{ $key }}'"
                        title="{{ $label }}"
                        class="relative flex h-14 cursor-pointer items-center justify-center text-zinc-400 transition-colors hover:text-zinc-700 dark:hover:text-zinc-200"
                        x-bind:class="tab === '{{ $key }}' && '!text-zinc-800 dark:!text-zinc-100'"
                    >
                        <flux:icon :name="$icon" class="size-5" />
                        <span class="sr-only">{{ $label }}</span>
                        <span class="absolute inset-x-5 bottom-0 h-0.5 bg-[#B3907A] transition-opacity" x-bind:class="tab === '{{ $key }}' ? 'opacity-100' : 'opacity-0'"></span>
                    </button>
                @endforeach
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                {{-- Tab: photos / sets --}}
                <div x-show="tab === 'photos'" x-data="{ adding: false }">
                    <div class="flex items-center justify-between px-6 pt-5 pb-3">
                        <span class="text-xs font-semibold tracking-widest text-zinc-500 uppercase">{{ __('Sets') }}</span>
                        <button
                            type="button"
                            x-on:click="adding = ! adding; $nextTick(() => adding && $refs.newAlbum.focus())"
                            class="flex cursor-pointer items-center gap-1.5 text-sm font-medium text-[#8a6a57] hover:text-[#6d5343]"
                        >
                            <flux:icon name="plus-circle" class="size-5" />
                            {{ __('Agregar set') }}
                        </button>
                    </div>

                    <form x-show="adding" x-cloak wire:submit="addAlbum" x-on:submit="adding = false" class="flex gap-2 px-6 pb-3">
                        <input
                            x-ref="newAlbum"
                            wire:model="newAlbumTitle"
                            x-on:keydown.escape="adding = false"
                            placeholder="{{ __('Nombre del set') }}"
                            class="h-9 min-w-0 flex-1 rounded-md border border-zinc-300 px-3 text-sm focus:border-[#B3907A] focus:outline-none dark:border-zinc-600 dark:bg-zinc-800"
                        >
                        <button type="submit" class="h-9 cursor-pointer rounded-md px-3 text-sm font-medium {{ $rust }}">{{ __('Agregar') }}</button>
                    </form>
                    @error('newAlbumTitle') <p class="px-6 pb-3 text-sm text-[#b42318]">{{ $message }}</p> @enderror

                    {{-- Sets: drag to reorder (mouse), or "Subir"/"Bajar" in each set's menu (touch). --}}
                    <ul
                        x-data="{
                            dragging: null,
                            initialOrder: null,
                            ids() {
                                return [...$el.querySelectorAll(':scope > [data-album-id]')].map((li) => Number(li.dataset.albumId));
                            },
                            start(event, li) {
                                this.dragging = li;
                                this.initialOrder = this.ids().join(',');
                                event.dataTransfer.effectAllowed = 'move';
                                event.dataTransfer.setData('text/plain', li.dataset.albumId);
                                requestAnimationFrame(() => li.classList.add('opacity-40'));
                            },
                            over(event, li) {
                                if (! this.dragging || li === this.dragging) return;

                                const rect = li.getBoundingClientRect();
                                const after = event.clientY > rect.top + rect.height / 2;
                                li.parentNode.insertBefore(this.dragging, after ? li.nextSibling : li);
                            },
                            async end() {
                                if (! this.dragging) return;

                                this.dragging.classList.remove('opacity-40');
                                this.dragging = null;

                                const order = this.ids();
                                if (order.join(',') !== this.initialOrder) await $wire.saveAlbumOrder(order);
                            },
                        }"
                        data-test="album-list"
                    >
                        @foreach ($this->albums as $album)
                            <li
                                wire:key="album-{{ $album->id }}"
                                data-album-id="{{ $album->id }}"
                                x-data="{
                                    editing: false,
                                    title: @js($album->title),
                                    saving: false,
                                    startRename() {
                                        this.title = @js($album->title);
                                        this.editing = true;
                                        this.$nextTick(() => { this.$refs.rename.focus(); this.$refs.rename.select(); });
                                    },
                                    async saveRename() {
                                        // Enter submits and then the input blurs as it hides; only save once.
                                        if (this.saving || ! this.editing) return;
                                        if (this.title.trim() === '' || this.title.trim() === @js($album->title)) {
                                            this.editing = false;
                                            return;
                                        }
                                        this.saving = true;
                                        try {
                                            await $wire.renameAlbum({{ $album->id }}, this.title);
                                        } finally {
                                            this.saving = false;
                                            this.editing = false;
                                        }
                                    },
                                }"
                                @if ($this->albums->count() > 1)
                                    x-bind:draggable="! editing"
                                    x-on:dragstart="start($event, $el)"
                                    x-on:dragover.prevent="over($event, $el)"
                                    x-on:drop.prevent
                                    x-on:dragend="end()"
                                @endif
                                @class([
                                    'group relative flex items-center gap-2 pe-3 transition-colors',
                                    'pointer-fine:cursor-grab pointer-fine:active:cursor-grabbing' => $this->albums->count() > 1,
                                    'bg-[#f5f1ea] dark:bg-zinc-800' => $album->id === $activeAlbumId,
                                    'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' => $album->id !== $activeAlbumId,
                                ])
                            >
                                @if ($this->albums->count() > 1)
                                    <flux:icon
                                        name="bars-2"
                                        class="pointer-events-none absolute top-1/2 left-1.5 hidden size-4 -translate-y-1/2 text-zinc-400 opacity-0 transition-opacity group-hover:opacity-100 pointer-fine:block"
                                    />
                                @endif

                                <form
                                    x-show="editing"
                                    x-cloak
                                    x-on:submit.prevent="saveRename()"
                                    class="flex min-w-0 flex-1 items-center gap-3 py-2.5 ps-6"
                                >
                                    <flux:icon name="folder" class="size-5 shrink-0 text-[#B3907A]" />
                                    <label for="rename-album-{{ $album->id }}" class="sr-only">{{ __('Nombre del set') }}</label>
                                    <input
                                        x-ref="rename"
                                        id="rename-album-{{ $album->id }}"
                                        x-model="title"
                                        x-on:keydown.escape.stop="editing = false"
                                        x-on:blur="saveRename()"
                                        maxlength="255"
                                        class="h-9 min-w-0 flex-1 rounded-md border border-[#B3907A] bg-white px-2.5 text-sm focus:ring-2 focus:ring-[#B3907A]/30 focus:outline-none dark:bg-zinc-800"
                                    >
                                </form>

                                <button
                                    type="button"
                                    x-show="! editing"
                                    wire:click="selectAlbum({{ $album->id }})"
                                    x-on:click="closeSidebarOnMobile()"
                                    x-on:dblclick="startRename()"
                                    class="flex min-w-0 flex-1 cursor-pointer items-center gap-3 py-4 ps-6 text-start"
                                >
                                    <flux:icon name="folder" @class(['size-5 shrink-0', 'text-[#B3907A]' => $album->id === $activeAlbumId, 'text-zinc-400' => $album->id !== $activeAlbumId]) />
                                    <span class="truncate">{{ $album->title }}</span>
                                    <span class="shrink-0 text-zinc-400">({{ $album->media_count }})</span>
                                </button>

                                <flux:dropdown position="bottom" align="end" x-show="! editing">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('Opciones del set')" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" x-on:click="startRename()">
                                            {{ __('Renombrar') }}
                                        </flux:menu.item>
                                        @if ($this->albums->count() > 1)
                                            <flux:menu.item icon="arrow-up" wire:click="moveAlbum({{ $album->id }}, -1)" :disabled="$loop->first">
                                                {{ __('Subir') }}
                                            </flux:menu.item>
                                            <flux:menu.item icon="arrow-down" wire:click="moveAlbum({{ $album->id }}, 1)" :disabled="$loop->last">
                                                {{ __('Bajar') }}
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item
                                                icon="trash"
                                                variant="danger"
                                                wire:click="deleteAlbum({{ $album->id }})"
                                                wire:confirm="{{ __('¿Eliminar este set y todo su contenido?') }}"
                                            >
                                                {{ __('Eliminar set') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </li>
                        @endforeach
                    </ul>
                    @error('albumTitle') <p class="px-6 py-2 text-sm text-[#b42318]">{{ $message }}</p> @enderror
                </div>

                {{-- Tab: design (cover layouts) --}}
                <div x-show="tab === 'design'" x-cloak class="flex flex-col gap-5 px-6 py-5">
                    <div class="flex items-center justify-between">
                        <p class="text-lg font-medium">{{ __('Portada') }}</p>
                        @if ($this->allPhotos->isNotEmpty())
                            <button
                                type="button"
                                x-on:click="$flux.modal('cover-picker').show()"
                                class="flex cursor-pointer items-center gap-1.5 text-sm font-medium text-[#8a6a57] hover:text-[#6d5343]"
                            >
                                <flux:icon name="photo" variant="micro" class="size-4" />
                                {{ __('Cambiar foto') }}
                            </button>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-x-3 gap-y-4">
                        @foreach (CoverLayout::cases() as $layout)
                            @php
                                $selected = $gallery->cover_layout === $layout;
                                $thumb = $this->coverImage ? route('media.show', $this->coverImage) : null;
                            @endphp
                            <button
                                type="button"
                                wire:click="setCoverLayout('{{ $layout->value }}')"
                                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                class="group flex cursor-pointer flex-col items-center gap-2"
                            >
                                <span @class([
                                    'block w-full border p-1 transition-colors',
                                    'border-[#B3907A] ring-1 ring-[#B3907A]' => $selected,
                                    'border-zinc-200 group-hover:border-zinc-400 dark:border-zinc-700' => ! $selected,
                                ])>
                                    <x-cover-layout-preview :layout="$layout" :image="$thumb" />
                                </span>
                                <span @class(['text-sm', 'font-semibold text-zinc-900 dark:text-white' => $selected, 'text-zinc-600 dark:text-zinc-300' => ! $selected])>
                                    {{ $layout->label() }}
                                </span>
                            </button>
                        @endforeach
                    </div>

                    <p class="text-sm text-zinc-500">{{ __('El diseño se aplica a la portada de la galería pública.') }}</p>

                    <flux:separator />

                    <button
                        type="button"
                        role="switch"
                        aria-checked="{{ $gallery->show_cover_in_gallery ? 'true' : 'false' }}"
                        wire:click="toggleCoverInGallery"
                        data-test="toggle-cover-in-gallery"
                        class="flex cursor-pointer items-start gap-3 text-start"
                    >
                        <span @class([
                            'relative mt-0.5 inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors',
                            'bg-[#B3907A]' => $gallery->show_cover_in_gallery,
                            'bg-zinc-300 dark:bg-zinc-600' => ! $gallery->show_cover_in_gallery,
                        ])>
                            <span @class([
                                'inline-block size-4 rounded-full bg-white shadow transition-transform',
                                'translate-x-4.5' => $gallery->show_cover_in_gallery,
                                'translate-x-0.5' => ! $gallery->show_cover_in_gallery,
                            ])></span>
                        </span>
                        <span class="flex flex-col gap-0.5">
                            <span class="text-sm font-medium">{{ __('Mostrar la portada también en la galería') }}</span>
                            <span class="text-sm text-zinc-500">
                                {{ $gallery->show_cover_in_gallery
                                    ? __('La foto de portada aparece arriba y también entre las demás fotos.')
                                    : __('La foto de portada solo aparece arriba, para que no se repita.') }}
                            </span>
                        </span>
                    </button>
                </div>

                {{-- Tab: settings --}}
                <div x-show="tab === 'settings'" x-cloak class="flex flex-col gap-6 px-6 py-5">
                    <form wire:submit="saveDetails" class="flex flex-col gap-4">
                        <p class="text-lg font-medium">{{ __('Detalles') }}</p>
                        <flux:input wire:model="title" :label="__('Título')" required />
                        <flux:input wire:model="client_name" :label="__('Cliente')" required />
                        <flux:input wire:model="location" :label="__('Lugar')" :badge="__('Opcional')" />
                        <div class="flex flex-col gap-2">
                            <flux:input wire:model="available_until" :label="__('Disponible hasta')" :badge="__('Opcional')" type="date" />
                            <flux:text class="text-sm text-zinc-500">
                                {{ __('Déjala vacía para que la galería esté disponible siempre.') }}
                                @if ($available_until)
                                    <button type="button" wire:click="$set('available_until', null)" class="cursor-pointer font-medium text-zinc-700 underline underline-offset-2 hover:text-zinc-900 dark:text-zinc-200">
                                        {{ __('Quitar fecha') }}
                                    </button>
                                @endif
                            </flux:text>
                        </div>
                        <flux:button type="submit" variant="primary" class="w-full">{{ __('Guardar cambios') }}</flux:button>
                    </form>

                    <flux:separator />

                    <div class="flex flex-col gap-2">
                        <p class="font-medium">{{ __('Eliminar galería') }}</p>
                        <flux:text class="text-sm text-zinc-500">{{ __('Esto elimina permanentemente la galería, sus sets y archivos.') }}</flux:text>
                        <flux:button
                            variant="danger"
                            wire:click="deleteGallery"
                            wire:confirm="{{ __('¿Eliminar esta galería de forma permanente? Se borrarán sus sets y archivos.') }}"
                        >
                            {{ __('Eliminar galería') }}
                        </flux:button>
                    </div>
                </div>
            </div>

            <div class="hidden shrink-0 border-t border-zinc-200 p-3 lg:block dark:border-zinc-700">
                <button
                    type="button"
                    x-on:click="sidebarOpen = false"
                    class="flex size-10 cursor-pointer items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800"
                    title="{{ __('Ocultar panel') }}"
                >
                    <flux:icon name="chevron-double-left" class="size-5" />
                </button>
            </div>
        </aside>

        {{-- ───────────── Media grid ───────────── --}}
        <main
            class="min-w-0 flex-1 overflow-y-auto"
            x-data="{
                size: (() => { try { return localStorage.getItem('editor-grid-size') || 'large' } catch (e) { return 'large' } })(),
                dragOverZone: false,
                upload: { state: 'idle', progress: 0, count: 0, error: null },
                setSize(size) {
                    this.size = size;
                    try { localStorage.setItem('editor-grid-size', size) } catch (e) {}
                },
                hideTimer: null,
                uploadFiles(fileList) {
                    const files = [...fileList].filter((file) => /^(image|video)\//.test(file.type));
                    if (! files.length) return;

                    clearTimeout(this.hideTimer);
                    this.upload = { state: 'uploading', progress: 0, count: files.length, error: null };

                    $wire.uploadMultiple('newFiles', files,
                        async () => {
                            this.upload.state = 'processing';
                            await $wire.uploadFiles();
                            const failed = $wire.$el.querySelector('[data-upload-error]');
                            this.upload.state = failed ? 'error' : 'done';
                            this.upload.error = failed?.textContent.trim() ?? null;

                            // Success hides itself after 5s; errors stay until dismissed so they aren't missed.
                            if (! failed) {
                                this.hideTimer = setTimeout(() => {
                                    if (this.upload.state === 'done') this.upload.state = 'idle';
                                }, 5000);
                            }
                        },
                        () => { this.upload.state = 'error'; this.upload.error = @js(__('No se pudo subir. Revisa que los archivos sean fotos o videos de hasta 100 MB.')); },
                        (event) => { this.upload.progress = event.detail.progress; },
                    );
                },
            }"
            x-on:dragover.prevent="if ([...$event.dataTransfer.types].includes('Files')) dragOverZone = true"
            x-on:dragleave="if (! $el.contains($event.relatedTarget)) dragOverZone = false"
            x-on:drop.prevent="dragOverZone = false; if ($event.dataTransfer.files.length) uploadFiles($event.dataTransfer.files)"
        >
            <div class="flex flex-col gap-6 px-4 py-6 sm:px-8 lg:px-10">
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        x-show="! sidebarOpen"
                        x-on:click="sidebarOpen = true"
                        class="hidden size-9 cursor-pointer lg:flex items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800"
                        title="{{ __('Mostrar panel') }}"
                    >
                        <flux:icon name="bars-3" class="size-5" />
                    </button>

                    <h1 class="min-w-0 truncate font-display text-2xl sm:text-3xl">{{ $this->activeAlbum?->title }}</h1>
                    <span class="hidden text-sm text-zinc-500 sm:inline">
                        {{ trans_choice(':count archivo|:count archivos', $this->activeAlbumMedia->count(), ['count' => $this->activeAlbumMedia->count()]) }}
                    </span>

                    <div class="ms-auto flex shrink-0 items-center gap-1">
                        @foreach (['large' => ['squares-2x2', __('Miniaturas grandes')], 'small' => ['table-cells', __('Miniaturas pequeñas')]] as $mode => [$icon, $label])
                            <button
                                type="button"
                                x-on:click="setSize('{{ $mode }}')"
                                title="{{ $label }}"
                                class="hidden size-9 cursor-pointer items-center justify-center rounded-lg text-zinc-400 sm:flex transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800"
                                x-bind:class="size === '{{ $mode }}' && '!text-zinc-800 bg-zinc-100 dark:bg-zinc-800 dark:!text-zinc-100'"
                            >
                                <flux:icon :name="$icon" class="size-5" />
                            </button>
                        @endforeach

                        <span class="mx-2 hidden h-6 w-px bg-zinc-200 sm:block dark:bg-zinc-700"></span>

                        <label class="flex h-9 cursor-pointer items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-[#8a6a57] hover:bg-[#B3907A]/10 hover:text-[#6d5343]">
                            <flux:icon name="plus-circle" class="size-5" />
                            <span>{{ __('Agregar multimedia') }}</span>
                            <input
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime"
                                class="sr-only"
                                data-test="media-upload-input"
                                x-on:change="uploadFiles($event.target.files); $event.target.value = ''"
                            >
                        </label>
                    </div>
                </div>

                {{-- Server-side validation errors surface in the upload panel. --}}
                @error('newFiles') <span data-upload-error class="hidden">{{ $message }}</span> @enderror
                @error('newFiles.*') <span data-upload-error class="hidden">{{ $message }}</span> @enderror

                @if ($this->activeAlbumMedia->isEmpty())
                    <label
                        class="flex cursor-pointer flex-col items-center gap-3 border-2 border-dashed px-6 py-24 text-center transition-colors"
                        x-bind:class="dragOverZone ? 'border-[#B3907A] bg-[#B3907A]/5' : 'border-zinc-300 dark:border-zinc-700'"
                    >
                        <flux:icon name="cloud-arrow-up" class="size-10 text-zinc-300" />
                        <span class="font-medium">{{ __('Arrastra tus fotos y videos aquí') }}</span>
                        <span class="text-sm text-zinc-500">{{ __('o haz clic para elegirlos · JPG, PNG, WEBP, MP4, MOV') }}</span>
                        <input
                            type="file"
                            multiple
                            accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime"
                            class="sr-only"
                            x-on:change="uploadFiles($event.target.files); $event.target.value = ''"
                        >
                    </label>
                @else
                    <div
                        wire:key="album-media-{{ $activeAlbumId }}-{{ $this->activeAlbumMedia->map(fn ($m) => "{$m->id}:{$m->position}:{$m->is_featured}")->implode("|") }}-{{ $this->coverImageId }}-{{ (int) $gallery->show_cover_in_gallery }}"
                        x-data="{
                            items: @js($this->activeAlbumMedia->map(fn ($media) => [
                                'id' => $media->id,
                                'name' => $media->original_name,
                                'url' => route('media.show', $media).($media->isVideo() ? '#t=0.1' : ''),
                                'isVideo' => $media->isVideo(),
                                'isFeatured' => $media->is_featured,
                                'isCover' => $media->id === $this->coverImageId,
                            ])->all()),
                            dragIndex: null,
                            dirty: false,
                            matches(item) {
                                const query = this.search.trim().toLowerCase();
                                return query === '' || item.name.toLowerCase().includes(query);
                            },
                            dragStart(index) {
                                if (this.search.trim() !== '') return;
                                this.dragIndex = index;
                            },
                            dragOver(index) {
                                if (this.dragIndex === null || this.dragIndex === index) return;

                                const moved = this.items.splice(this.dragIndex, 1)[0];
                                this.items.splice(index, 0, moved);
                                this.dragIndex = index;
                                this.dirty = true;
                            },
                            async dragEnd() {
                                this.dragIndex = null;
                                if (! this.dirty) return;
                                this.dirty = false;
                                await $wire.saveMediaOrder(this.items.map((item) => item.id));
                            },
                        }"
                        class="flex flex-col gap-3"
                    >
                        <div
                            class="grid gap-3 sm:gap-5"
                            x-bind:class="size === 'large'
                                ? 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6'
                                : 'grid-cols-2 sm:grid-cols-5 lg:grid-cols-6 2xl:grid-cols-9'"
                        >
                            <template x-for="(item, index) in items" :key="item.id">
                                <div
                                    x-show="matches(item)"
                                    draggable="true"
                                    x-on:dragstart="dragStart(index)"
                                    x-on:dragover.prevent="dragOver(index)"
                                    x-on:dragend="dragEnd()"
                                    class="group relative aspect-square cursor-grab bg-[#f5f2ec] active:cursor-grabbing dark:bg-zinc-800"
                                    x-bind:class="dragIndex === index && 'opacity-40'"
                                    x-bind:title="item.name"
                                >
                                    <div class="flex size-full items-center justify-center p-[6%]">
                                        <template x-if="item.isVideo">
                                            <div class="relative flex size-full items-center justify-center">
                                                <video :src="item.url" preload="metadata" muted playsinline class="max-h-full max-w-full object-contain"></video>
                                                <flux:icon name="play-circle" class="absolute size-10 text-white drop-shadow" />
                                            </div>
                                        </template>
                                        <template x-if="! item.isVideo">
                                            <img :src="item.url" loading="lazy" alt="" draggable="false" class="max-h-full max-w-full object-contain shadow-sm">
                                        </template>
                                    </div>

                                    {{-- Status badges --}}
                                    <div class="pointer-events-none absolute top-2 left-2 flex gap-1">
                                        <span
                                            x-show="item.isCover"
                                            class="rounded-full bg-[#3d3835]/85 px-2 py-0.5 text-[11px] font-medium text-white"
                                            title="{{ $gallery->show_cover_in_gallery ? __('También se muestra dentro de la galería') : __('No se muestra dentro de la galería, solo en la portada') }}"
                                        >{{ $gallery->show_cover_in_gallery ? __('Portada') : __('Solo portada') }}</span>
                                        <span x-show="item.isFeatured" class="flex items-center gap-1 rounded-full bg-[#B3907A] px-2 py-0.5 text-[11px] font-medium text-white">
                                            <flux:icon name="sparkles" variant="micro" class="size-3" />
                                            {{ __('Destacada') }}
                                        </span>
                                    </div>

                                    {{-- Actions: on hover (mouse) or always visible (touch) --}}
                                    <div
                                        x-data="{ more: false }"
                                        x-on:click.outside="more = false"
                                        class="absolute right-2 bottom-2 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100 pointer-coarse:opacity-100"
                                    >
                                        <button
                                            type="button"
                                            x-show="! item.isVideo && ! item.isCover"
                                            x-on:click="$wire.setCover(item.id); more = false"
                                            title="{{ __('Usar como portada') }}"
                                            class="flex size-8 cursor-pointer items-center justify-center rounded-full bg-white/95 text-zinc-700 shadow-sm hover:bg-white pointer-coarse:hidden"
                                            x-bind:class="more && 'pointer-coarse:!flex'"
                                        >
                                            <flux:icon name="photo" class="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            x-on:click="$wire.toggleFeatured(item.id)"
                                            x-bind:title="item.isFeatured ? @js(__('Quitar de destacadas')) : @js(__('Marcar como destacada'))"
                                            class="flex size-8 cursor-pointer items-center justify-center rounded-full shadow-sm"
                                            x-bind:class="item.isFeatured ? 'bg-[#B3907A] text-white' : 'bg-white/95 text-zinc-700 hover:bg-white'"
                                        >
                                            <flux:icon name="sparkles" class="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            x-on:click="more = false; if (confirm(@js(__('¿Eliminar este archivo?')))) $wire.deleteMedia(item.id)"
                                            title="{{ __('Eliminar') }}"
                                            class="flex size-8 cursor-pointer items-center justify-center rounded-full bg-white/95 text-zinc-700 shadow-sm hover:bg-white hover:text-[#b42318] pointer-coarse:hidden"
                                            x-bind:class="more && 'pointer-coarse:!flex'"
                                        >
                                            <flux:icon name="trash" class="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            x-on:click="more = ! more"
                                            x-bind:aria-expanded="more"
                                            title="{{ __('Más acciones') }}"
                                            class="hidden size-8 cursor-pointer items-center justify-center rounded-full bg-white/95 text-zinc-700 shadow-sm pointer-coarse:flex"
                                        >
                                            <flux:icon name="ellipsis-horizontal" class="size-4" x-show="! more" />
                                            <flux:icon name="x-mark" class="size-4" x-show="more" x-cloak />
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <p x-show="items.length && ! items.some((item) => matches(item))" x-cloak class="py-10 text-center text-zinc-500">
                            {{ __('Ningún archivo coincide con tu búsqueda.') }}
                        </p>
                        <p class="text-center text-sm text-zinc-400 pointer-coarse:hidden">{{ __('Arrastra las fotos para cambiar su orden. También puedes soltar archivos aquí para subirlos.') }}</p>
                    </div>
                @endif
            </div>

            {{-- Drop overlay --}}
            <div
                x-show="dragOverZone"
                x-cloak
                class="pointer-events-none fixed inset-x-0 top-16 bottom-0 z-10 flex items-center justify-center border-4 border-dashed border-[#B3907A] bg-[#B3907A]/10 lg:left-80"
                x-bind:class="! sidebarOpen && '!left-0'"
            >
                <p class="rounded-full bg-white px-5 py-2.5 font-medium shadow-lg dark:bg-zinc-900">{{ __('Suelta para subir a este set') }}</p>
            </div>

            {{-- Upload status panel --}}
            <div
                x-show="upload.state !== 'idle'"
                x-cloak
                x-transition
                class="fixed inset-x-0 bottom-0 z-40 flex items-center gap-3 border-t border-zinc-200 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_-12px_rgba(0,0,0,0.2)] sm:inset-x-auto sm:right-4 sm:bottom-4 sm:w-[22rem] sm:border sm:pb-4 sm:shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                role="status"
            >
                <template x-if="upload.state === 'uploading' || upload.state === 'processing'">
                    <flux:icon.loading class="size-5 shrink-0 text-[#B3907A]" />
                </template>
                <template x-if="upload.state === 'done'">
                    <flux:icon name="check" class="size-5 shrink-0 text-[#2f6b3a]" />
                </template>
                <template x-if="upload.state === 'error'">
                    <flux:icon name="exclamation-triangle" class="size-5 shrink-0 text-[#b42318]" />
                </template>

                <div class="min-w-0 flex-1">
                    <p class="font-medium" x-text="{
                        uploading: @js(__('Subiendo…')) + ' ' + upload.progress + '%',
                        processing: @js(__('Procesando…')),
                        done: @js(__('Carga completada')),
                        error: @js(__('No se pudo completar la carga')),
                    }[upload.state]"></p>
                    <p class="truncate text-sm text-zinc-500" x-show="upload.state !== 'error'" x-text="upload.count + ' ' + (upload.count === 1 ? @js(__('elemento')) : @js(__('elementos')))"></p>
                    <p class="text-sm text-[#b42318]" x-show="upload.state === 'error'" x-text="upload.error"></p>
                    <div x-show="upload.state === 'uploading'" class="mt-2 h-1 overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full bg-[#B3907A] transition-[width]" x-bind:style="`width: ${upload.progress}%`"></div>
                    </div>
                </div>

                <button
                    type="button"
                    x-show="upload.state === 'done' || upload.state === 'error'"
                    x-on:click="upload.state = 'idle'"
                    class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800"
                    aria-label="{{ __('Cerrar') }}"
                >
                    <flux:icon name="x-mark" class="size-4" />
                </button>
            </div>
        </main>
    </div>

    {{-- ───────────── Share modal ───────────── --}}
    <flux:modal name="share" class="w-full max-w-lg" x-on:close="$wire.revealedCode && $wire.dismissRevealedCode()">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Compartir galería') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Envía el enlace a tu cliente. Para ver las fotos no destacadas necesitará el código de desbloqueo.') }}</flux:text>
            </div>

            @unless ($published)
                <div class="flex flex-col gap-3 border border-[#C1B6A4] bg-[#EFE7DA] p-4 sm:flex-row sm:items-center">
                    <flux:icon name="eye-slash" class="size-5 shrink-0 text-[#6d6058]" />
                    <p class="flex-1 text-sm text-[#3d3835]">
                        {{ __('Esta galería es un borrador: nadie más puede abrir el enlace todavía.') }}
                    </p>
                    <button
                        type="button"
                        wire:click="setStatus('published')"
                        class="h-9 shrink-0 cursor-pointer rounded-md px-4 text-sm font-semibold {{ $rust }}"
                    >
                        {{ __('Publicar') }}
                    </button>
                </div>
            @endunless

            <flux:input :label="__('Enlace de la galería')" :value="route('galleries.show', $gallery)" readonly copyable />

            <div class="flex flex-col gap-3">
                <flux:heading>{{ __('Código de desbloqueo') }}</flux:heading>

                @if ($revealedCode)
                    <div class="flex flex-col gap-2 border-2 border-[#B3907A] bg-[#B3907A]/10 p-4">
                        <flux:text class="font-medium">{{ __('Este es tu código — guárdalo ahora, no se volverá a mostrar:') }}</flux:text>
                        <flux:input :value="$revealedCode" readonly copyable class="font-mono" />
                    </div>
                @else
                    <flux:text class="text-sm">
                        {{ __('Por seguridad, el código no se puede volver a mostrar. Si lo perdiste, genera uno nuevo (el anterior dejará de funcionar).') }}
                    </flux:text>
                    <div>
                        <flux:button
                            variant="danger"
                            size="sm"
                            wire:click="regenerateCode"
                            wire:confirm="{{ __('¿Regenerar el código? El código anterior dejará de funcionar.') }}"
                        >
                            {{ __('Regenerar código') }}
                        </flux:button>
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary">{{ $revealedCode ? __('Ya lo guardé') : __('Listo') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- ───────────── Cover picker ───────────── --}}
    <flux:modal name="cover-picker" class="w-full max-w-3xl">
        <div class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ __('Elegir portada') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Elige la foto que se verá al abrir la galería.') }}</flux:text>
            </div>

            <div class="grid max-h-[60vh] grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-4">
                @foreach ($this->allPhotos as $photo)
                    <button
                        type="button"
                        wire:key="cover-option-{{ $photo->id }}"
                        wire:click="setCover({{ $photo->id }})"
                        x-on:click="$flux.modal('cover-picker').close()"
                        @class([
                            'relative aspect-square cursor-pointer overflow-hidden',
                            'ring-3 ring-[#B3907A] ring-offset-2' => $photo->id === $this->coverImageId,
                        ])
                    >
                        <img src="{{ route('media.show', $photo) }}" alt="{{ $photo->original_name }}" loading="lazy" class="size-full object-cover transition hover:opacity-85">
                    </button>
                @endforeach
            </div>
        </div>
    </flux:modal>
</div>
