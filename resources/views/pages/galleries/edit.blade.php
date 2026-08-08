<?php

use App\Concerns\GalleryValidationRules;
use App\Enums\MediaType;
use App\Models\Album;
use App\Models\Gallery;
use App\Models\Media;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Editar galería')] class extends Component {
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
    public function coverImageId(): ?int
    {
        return $this->gallery->coverImage()?->id;
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

        unset($this->albums);

        Flux::toast(variant: 'success', text: __('Álbum eliminado.'));
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

        $this->newFiles = [];
        unset($this->albums, $this->coverImageId);

        Flux::toast(variant: 'success', text: __('Archivos subidos.'));
    }

    public function toggleFeatured(int $mediaId): void
    {
        $media = $this->findMedia($mediaId);

        $media->update(['is_featured' => ! $media->is_featured]);

        unset($this->albums, $this->coverImageId);
    }

    public function setCover(int $mediaId): void
    {
        $media = $this->findMedia($mediaId);

        abort_unless($media->isPhoto(), 422);

        $this->gallery->update(['cover_media_id' => $media->id]);

        unset($this->coverImageId);

        Flux::toast(variant: 'success', text: __('Portada actualizada.'));
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

        unset($this->albums, $this->coverImageId);

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

        $this->gallery->update($validated);

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

<div class="flex flex-col gap-8">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $gallery->title }}</flux:heading>
                <flux:text class="text-zinc-500">{{ $gallery->client_name }}</flux:text>
            </div>
            <flux:button :href="route('galleries.show', $gallery)" variant="ghost" icon="eye" target="_blank">
                {{ __('Ver galería pública') }}
            </flux:button>
        </div>

        {{-- Details --}}
        <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading class="mb-4">{{ __('Detalles') }}</flux:heading>
            <form wire:submit="saveDetails" class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="title" :label="__('Título')" required />
                    <flux:input wire:model="client_name" :label="__('Cliente')" required />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="available_until" :label="__('Disponible hasta')" type="date" />
                    <flux:input wire:model="location" :label="__('Lugar')" />
                </div>
                <flux:text class="text-sm text-zinc-500">
                    {{ __('Creada el :date', ['date' => $gallery->created_at->translatedFormat('d M Y')]) }}
                </flux:text>
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('Guardar cambios') }}</flux:button>
                </div>
            </form>
        </section>

        <div class="grid gap-8 sm:grid-cols-2">
            {{-- Public link --}}
            <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading class="mb-2">{{ __('Enlace de la galería') }}</flux:heading>
                <flux:text class="mb-4 text-zinc-500">
                    {{ __('Compártelo con tu cliente para que vea la galería directamente.') }}
                </flux:text>

                <flux:input :value="route('galleries.show', $gallery)" readonly copyable />
            </section>

            {{-- Unlock code --}}
            <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading class="mb-2">{{ __('Código de desbloqueo') }}</flux:heading>
                <flux:text class="mb-4 text-zinc-500">
                    {{ __('Por seguridad, el código no se puede volver a mostrar más tarde. Puedes generar uno nuevo (invalida el anterior).') }}
                </flux:text>

                @if ($revealedCode)
                    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border-2 border-accent bg-accent/10 p-4">
                        <div class="flex-1">
                            <flux:text class="mb-1 font-medium">{{ __('Este es tu código — guárdalo ahora, no se volverá a mostrar:') }}</flux:text>
                            <flux:input :value="$revealedCode" readonly copyable class="max-w-xs font-mono" />
                        </div>
                        <flux:button size="sm" variant="ghost" wire:click="dismissRevealedCode">
                            {{ __('Ya lo guardé') }}
                        </flux:button>
                    </div>
                @endif

                <flux:button
                    variant="danger"
                    wire:click="regenerateCode"
                    wire:confirm="{{ __('¿Regenerar el código? El código anterior dejará de funcionar.') }}"
                >
                    {{ __('Regenerar código') }}
                </flux:button>
            </section>
        </div>

        {{-- Albums --}}
        <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading>{{ __('Álbumes') }}</flux:heading>
            </div>

            <div class="mb-4 flex flex-wrap gap-2">
                @foreach ($this->albums as $album)
                    <div class="flex items-center gap-1">
                        <flux:button
                            size="sm"
                            :variant="$album->id === $activeAlbumId ? 'primary' : 'ghost'"
                            wire:click="selectAlbum({{ $album->id }})"
                        >
                            {{ $album->title }} ({{ $album->media_count }})
                        </flux:button>
                        @if ($this->albums->count() > 1)
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="deleteAlbum({{ $album->id }})"
                                wire:confirm="{{ __('¿Eliminar este álbum y todo su contenido?') }}"
                            />
                        @endif
                    </div>
                @endforeach
            </div>

            <form wire:submit="addAlbum" class="flex max-w-sm gap-2">
                <flux:input wire:model="newAlbumTitle" :placeholder="__('Nombre del nuevo álbum')" />
                <flux:button type="submit">{{ __('Agregar') }}</flux:button>
            </form>
        </section>

        {{-- Media in active album --}}
        @if ($this->activeAlbum)
            <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading class="mb-4">{{ $this->activeAlbum->title }}</flux:heading>

                <form wire:submit="uploadFiles" class="mb-6 flex flex-col gap-4">
                    <div class="flex flex-wrap items-end gap-4">
                        <flux:input type="file" wire:model="newFiles" multiple :label="__('Subir fotos/videos')" />
                        <flux:button
                            type="submit"
                            variant="primary"
                            wire:loading.attr="disabled"
                            wire:target="newFiles,uploadFiles"
                        >
                            {{ __('Subir') }}
                        </flux:button>
                    </div>

                    @if (! empty($newFiles))
                        <div class="flex flex-wrap gap-2">
                            @foreach ($newFiles as $index => $file)
                                <span class="flex items-center gap-2 rounded-full bg-zinc-100 py-1 pr-1 pl-3 text-sm text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                    {{ $file->getClientOriginalName() }}
                                    <button
                                        type="button"
                                        wire:click="removePendingFile({{ $index }})"
                                        class="flex size-5 items-center justify-center rounded-full text-zinc-500 hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-100"
                                        aria-label="{{ __('Quitar') }}"
                                    >
                                        <flux:icon name="x-mark" class="size-3" />
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </form>
                <div wire:loading wire:target="newFiles" class="mb-4 text-sm text-zinc-500">
                    {{ __('Cargando archivo(s), espera antes de darle a Subir...') }}
                </div>
                @error('newFiles')
                    <flux:text class="mb-4 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror
                @error('newFiles.*')
                    <flux:text class="mb-4 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror

                @if ($this->activeAlbumMedia->isEmpty())
                    <flux:text class="text-zinc-500">{{ __('Este álbum aún no tiene archivos.') }}</flux:text>
                @else
                    <div
                        wire:key="album-media-{{ $activeAlbumId }}-{{ $this->activeAlbumMedia->map(fn ($m) => "{$m->id}:{$m->position}:{$m->is_featured}")->implode('|') }}-{{ $this->coverImageId }}"
                        x-data="{
                            items: @js($this->activeAlbumMedia->map(fn ($media) => [
                                'id' => $media->id,
                                'url' => route('media.show', $media).($media->isVideo() ? '#t=0.1' : ''),
                                'isVideo' => $media->isVideo(),
                                'isFeatured' => $media->is_featured,
                                'isCover' => $media->id === $this->coverImageId,
                            ])->all()),
                            dragIndex: null,
                            dirty: false,
                            dragStart(index) {
                                this.dragIndex = index;
                            },
                            dragOver(index) {
                                if (this.dragIndex === null || this.dragIndex === index) return;

                                const moved = this.items.splice(this.dragIndex, 1)[0];
                                this.items.splice(index, 0, moved);
                                this.dragIndex = index;
                                this.dirty = true;
                            },
                            dragEnd() {
                                this.dragIndex = null;
                            },
                            async save() {
                                await $wire.saveMediaOrder(this.items.map((item) => item.id));
                                this.dirty = false;
                            },
                        }"
                        class="flex flex-col gap-4"
                    >
                        <div class="flex items-center justify-between">
                            <flux:text class="text-sm text-zinc-500">
                                {{ __('Arrastra los archivos para cambiar su orden.') }}
                            </flux:text>
                            <flux:button size="sm" variant="primary" x-show="dirty" x-cloak x-on:click="save()">
                                {{ __('Guardar orden') }}
                            </flux:button>
                        </div>

                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                            <template x-for="(item, index) in items" :key="item.id">
                                <div
                                    draggable="true"
                                    x-on:dragstart="dragStart(index)"
                                    x-on:dragover.prevent="dragOver(index)"
                                    x-on:dragend="dragEnd()"
                                    class="relative cursor-grab overflow-hidden rounded-lg border border-zinc-200 active:cursor-grabbing dark:border-zinc-700"
                                >
                                    <div x-show="item.isVideo" class="relative flex aspect-square items-center justify-center bg-zinc-800">
                                        <video
                                            x-show="item.isVideo"
                                            :src="item.url"
                                            preload="metadata"
                                            muted
                                            playsinline
                                            class="absolute inset-0 h-full w-full object-cover"
                                        ></video>
                                        <flux:icon name="play-circle" class="relative size-10 text-white drop-shadow" />
                                    </div>

                                    <div x-show="!item.isVideo" class="relative">
                                        <img :src="item.url" class="aspect-square w-full object-cover" alt="" draggable="false">

                                        <button
                                            type="button"
                                            x-on:click="$wire.setCover(item.id)"
                                            title="{{ __('Usar como portada') }}"
                                            class="absolute top-2 right-2 flex size-8 items-center justify-center rounded-full shadow-sm transition-colors"
                                            x-bind:class="item.isCover ? 'bg-accent text-white' : 'bg-white/90 text-zinc-700 hover:bg-white dark:bg-zinc-900/90 dark:text-zinc-200'"
                                        >
                                            <flux:icon name="star" variant="solid" x-show="item.isCover" class="size-4" />
                                            <flux:icon name="star" variant="outline" x-show="!item.isCover" class="size-4" />
                                        </button>
                                    </div>

                                    <div class="flex items-center justify-between gap-1 p-2">
                                        <button
                                            type="button"
                                            x-on:click="$wire.toggleFeatured(item.id)"
                                            class="rounded-full px-2.5 py-1 text-xs font-medium transition-colors"
                                            x-bind:class="item.isFeatured ? 'bg-accent text-white' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'"
                                        >
                                            {{ __('Destacada') }}
                                        </button>
                                        <button
                                            type="button"
                                            x-on:click="if (confirm('{{ __('¿Eliminar este archivo?') }}')) $wire.deleteMedia(item.id)"
                                            class="flex size-8 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                            title="{{ __('Eliminar') }}"
                                        >
                                            <flux:icon name="trash" class="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        {{-- Danger zone --}}
        <section class="rounded-xl border border-red-200 p-6 dark:border-red-900">
            <flux:heading class="mb-2">{{ __('Eliminar galería') }}</flux:heading>
            <flux:text class="mb-4 text-zinc-500">
                {{ __('Esto elimina permanentemente la galería, sus álbumes y archivos.') }}
            </flux:text>
            <flux:button
                variant="danger"
                wire:click="deleteGallery"
                wire:confirm="{{ __('¿Eliminar esta galería de forma permanente?') }}"
            >
                {{ __('Eliminar galería') }}
            </flux:button>
        </section>
    </div>
