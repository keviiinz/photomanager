<?php

use App\Models\Gallery;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mis galerías')] class extends Component {
    public string $slug = '';

    /**
     * @return \Illuminate\Support\Collection<int, Gallery>
     */
    #[Computed]
    public function galleries()
    {
        return Auth::user()->savedGalleries()->with('photographer')->latest('gallery_user.created_at')->get();
    }

    public function addGallery(): void
    {
        $this->validate(['slug' => ['required', 'string']]);

        $gallery = Gallery::where('slug', $this->extractSlug($this->slug))->first();

        if (! $gallery) {
            $this->addError('slug', __('No encontramos una galería con ese enlace.'));

            return;
        }

        if ($gallery->isSavedFor(Auth::user())) {
            $this->addError('slug', __('Ya tienes esta galería en tu colección.'));

            return;
        }

        $gallery->saveFor(Auth::user());

        activity('gallery')
            ->causedBy(Auth::user())
            ->performedOn($gallery)
            ->event('saved')
            ->log("Agregó la galería \"{$gallery->title}\" a su colección");

        $this->slug = '';
        unset($this->galleries);

        Flux::toast(variant: 'success', text: __('Galería agregada a tu colección.'));
    }

    /**
     * Accepts either a bare slug ("boda-de-ana-ab12cd") or a full gallery
     * link ("https://.../g/boda-de-ana-ab12cd") and returns the slug.
     */
    protected function extractSlug(string $value): string
    {
        $value = trim($value);
        $path = parse_url($value, PHP_URL_PATH) ?: $value;
        $segments = array_values(array_filter(explode('/', $path)));

        return $segments === [] ? $value : end($segments);
    }
}; ?>

<div class="flex flex-col gap-8">
    <flux:heading size="xl">{{ __('Mis galerías') }}</flux:heading>

    <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading class="mb-4">{{ __('Agregar una galería') }}</flux:heading>
        <flux:text class="mb-4 text-zinc-500">
            {{ __('Pega el enlace que te compartió tu fotógrafo. Vas a ver las fotos destacadas de inmediato; para ver el resto, ingresa el código de desbloqueo dentro de la galería.') }}
        </flux:text>
        <form wire:submit="addGallery" class="flex flex-wrap items-end gap-4">
            <flux:input wire:model="slug" :label="__('Enlace de la galería')" placeholder="http://tusitio.com/g/boda-de-ana-y-marco-ab12cd" class="max-w-xs" />
            <flux:button type="submit" variant="primary">{{ __('Agregar') }}</flux:button>
        </form>
        @error('slug')
            <flux:text class="mt-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </section>

    @if ($this->galleries->isEmpty())
        <flux:text class="text-zinc-500">{{ __('Aún no has agregado ninguna galería.') }}</flux:text>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->galleries as $gallery)
                <a href="{{ route('galleries.show', $gallery) }}" wire:navigate
                    class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 hover:border-accent dark:border-zinc-700">
                    <flux:heading>{{ $gallery->title }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500">{{ $gallery->photographer->name }}</flux:text>
                    @if ($gallery->pivot->unlocked_at)
                        <flux:badge size="sm" color="lime" class="mt-1 w-fit">{{ __('Desbloqueada') }}</flux:badge>
                    @else
                        <flux:badge size="sm" color="zinc" class="mt-1 w-fit">{{ __('Solo fotos destacadas') }}</flux:badge>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
