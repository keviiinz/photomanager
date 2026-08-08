<?php

use App\Models\HomeImage;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Imágenes del inicio')] class extends Component {
    use WithFileUploads;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newImages = [];

    /**
     * @return \Illuminate\Support\Collection<int, HomeImage>
     */
    #[Computed]
    public function images()
    {
        return HomeImage::orderBy('position')->get();
    }

    public function uploadImages(): void
    {
        $this->validate([
            'newImages' => ['required', 'array', 'min:1'],
            'newImages.*' => ['image', 'max:20480'],
        ]);

        $disk = config('filesystems.default');
        $position = ((int) HomeImage::max('position')) + 1;
        $needsPrimary = ! HomeImage::where('is_primary', true)->exists();

        foreach ($this->newImages as $file) {
            $path = $file->store('home', $disk);

            HomeImage::create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'position' => $position++,
                'is_primary' => $needsPrimary,
            ]);

            $needsPrimary = false;
        }

        $this->newImages = [];
        unset($this->images);

        Flux::toast(variant: 'success', text: __('Imágenes subidas.'));
    }

    public function setPrimary(int $imageId): void
    {
        HomeImage::query()->update(['is_primary' => false]);
        HomeImage::whereKey($imageId)->update(['is_primary' => true]);

        unset($this->images);

        Flux::toast(variant: 'success', text: __('Imagen principal actualizada.'));
    }

    public function deleteImage(int $imageId): void
    {
        $image = HomeImage::findOrFail($imageId);
        $wasPrimary = $image->is_primary;

        Storage::disk($image->disk)->delete($image->path);
        $image->delete();

        if ($wasPrimary) {
            HomeImage::orderBy('position')->first()?->update(['is_primary' => true]);
        }

        unset($this->images);

        Flux::toast(variant: 'success', text: __('Imagen eliminada.'));
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function saveOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            HomeImage::whereKey($id)->update(['position' => $index]);
        }

        unset($this->images);

        Flux::toast(variant: 'success', text: __('Orden guardado.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Imágenes del inicio') }}</flux:heading>
        <flux:text class="text-zinc-500">
            {{ __('Estas son las fotos que se muestran en el carrusel de la página de inicio. La imagen marcada como principal es la que aparece en la pantalla de bienvenida.') }}
        </flux:text>
    </div>

    <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <form wire:submit="uploadImages" class="flex flex-wrap items-end gap-4">
            <flux:input type="file" wire:model="newImages" multiple accept="image/*" :label="__('Subir imágenes')" />
            <flux:button
                type="submit"
                variant="primary"
                wire:loading.attr="disabled"
                wire:target="newImages,uploadImages"
            >
                {{ __('Subir') }}
            </flux:button>
        </form>
        <div wire:loading wire:target="newImages" class="mt-4 text-sm text-zinc-500">
            {{ __('Cargando imagen(es), espera antes de darle a Subir...') }}
        </div>
        @error('newImages')
            <flux:text class="mt-4 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
        @error('newImages.*')
            <flux:text class="mt-4 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </section>

    @if ($this->images->isEmpty())
        <flux:text class="text-zinc-500">{{ __('Todavía no has subido ninguna imagen.') }}</flux:text>
    @else
        <section
            wire:key="home-images-{{ $this->images->map(fn ($image) => "{$image->id}:{$image->position}:{$image->is_primary}")->implode('|') }}"
            x-data="{
                images: @js($this->images->map(fn ($image) => ['id' => $image->id, 'url' => route('home-images.show', $image), 'isPrimary' => $image->is_primary])->all()),
                dragIndex: null,
                dirty: false,
                dragStart(index) {
                    this.dragIndex = index;
                },
                dragOver(index) {
                    if (this.dragIndex === null || this.dragIndex === index) return;

                    const moved = this.images.splice(this.dragIndex, 1)[0];
                    this.images.splice(index, 0, moved);
                    this.dragIndex = index;
                    this.dirty = true;
                },
                dragEnd() {
                    this.dragIndex = null;
                },
                async save() {
                    await $wire.saveOrder(this.images.map((image) => image.id));
                    this.dirty = false;
                },
            }"
            class="flex flex-col gap-4"
        >
            <div class="flex items-center justify-between">
                <flux:text class="text-sm text-zinc-500">
                    {{ __('Arrastra las imágenes para cambiar el orden en el que aparecen en el carrusel.') }}
                </flux:text>
                <flux:button size="sm" variant="primary" x-show="dirty" x-cloak x-on:click="save()">
                    {{ __('Guardar orden') }}
                </flux:button>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <template x-for="(image, index) in images" :key="image.id">
                    <div
                        draggable="true"
                        x-on:dragstart="dragStart(index)"
                        x-on:dragover.prevent="dragOver(index)"
                        x-on:dragend="dragEnd()"
                        class="group relative cursor-grab overflow-hidden rounded-lg border border-zinc-200 active:cursor-grabbing dark:border-zinc-700"
                    >
                        <img :src="image.url" class="aspect-square w-full object-cover" alt="" draggable="false">

                        <button
                            type="button"
                            x-on:click="$wire.setPrimary(image.id)"
                            title="{{ __('Usar como principal') }}"
                            class="absolute top-2 right-2 flex size-8 items-center justify-center rounded-full shadow-sm transition-colors"
                            x-bind:class="image.isPrimary ? 'bg-accent text-white' : 'bg-white/90 text-zinc-700 hover:bg-white dark:bg-zinc-900/90 dark:text-zinc-200'"
                        >
                            <flux:icon name="star" variant="solid" x-show="image.isPrimary" class="size-4" />
                            <flux:icon name="star" variant="outline" x-show="!image.isPrimary" class="size-4" />
                        </button>

                        <button
                            type="button"
                            x-on:click="if (confirm('{{ __('¿Eliminar esta imagen?') }}')) $wire.deleteImage(image.id)"
                            title="{{ __('Eliminar') }}"
                            class="absolute bottom-2 left-2 flex size-8 items-center justify-center rounded-full bg-white/90 text-zinc-700 opacity-0 shadow-sm transition-opacity group-hover:opacity-100 hover:bg-white dark:bg-zinc-900/90 dark:text-zinc-200"
                        >
                            <flux:icon name="trash" class="size-4" />
                        </button>
                    </div>
                </template>
            </div>
        </section>
    @endif
</div>
