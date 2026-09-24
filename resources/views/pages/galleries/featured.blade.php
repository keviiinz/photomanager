<?php

use App\Concerns\BrowsesPhotographerMedia;
use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Destacados')] class extends Component {
    use BrowsesPhotographerMedia {
        mediaQuery as allMediaQuery;
    }

    /**
     * @return Builder<Media>
     */
    protected function mediaQuery(): Builder
    {
        return $this->allMediaQuery()->where('is_featured', true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1">
        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <flux:heading size="xl" class="font-display !text-3xl !font-normal">{{ __('Destacados') }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ trans_choice(':count archivo destacado|:count archivos destacados', $this->total, ['count' => $this->total]) }}
            </flux:text>
        </div>
        <flux:text class="text-sm text-zinc-500">
            {{ __('Las fotos destacadas son visibles para tus clientes sin necesidad de desbloquear la galería.') }}
        </flux:text>
    </div>

    <x-media-library-grid
        :media="$this->media"
        :total="$this->total"
        :empty="__('Aún no tienes fotos destacadas. Márcalas desde la Biblioteca o desde cada galería.')"
    />
</div>
