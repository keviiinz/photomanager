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
        unset($this->albums);

        Flux::toast(variant: 'success', text: __('Archivos subidos.'));
    }

    public function toggleFeatured(int $mediaId): void
    {
        $media = $this->findMedia($mediaId);

        $media->update(['is_featured' => ! $media->is_featured]);

        unset($this->albums);
    }

    public function deleteMedia(int $mediaId): void
    {
        $media = $this->findMedia($mediaId);

        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        unset($this->albums);

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

                <form wire:submit="uploadFiles" class="mb-6 flex flex-wrap items-end gap-4">
                    <flux:input type="file" wire:model="newFiles" multiple :label="__('Subir fotos/videos')" />
                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="newFiles,uploadFiles"
                    >
                        {{ __('Subir') }}
                    </flux:button>
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
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($this->activeAlbumMedia as $media)
                            <div class="relative overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                                @if ($media->isVideo())
                                    <div class="flex aspect-square items-center justify-center bg-zinc-800">
                                        <flux:icon name="play-circle" class="size-10 text-white" />
                                    </div>
                                @else
                                    <img src="{{ route('media.show', $media) }}" class="aspect-square w-full object-cover" alt="">
                                @endif

                                <div class="flex items-center justify-between gap-1 p-2">
                                    <flux:switch
                                        wire:click="toggleFeatured({{ $media->id }})"
                                        :checked="$media->is_featured"
                                        :label="__('Destacada')"
                                    />
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="deleteMedia({{ $media->id }})"
                                        wire:confirm="{{ __('¿Eliminar este archivo?') }}"
                                    />
                                </div>
                            </div>
                        @endforeach
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
