<?php

use App\Concerns\BrowsesPhotographerMedia;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Biblioteca')] class extends Component {
    use BrowsesPhotographerMedia;
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
        <flux:heading size="xl" class="font-display !text-3xl !font-normal">{{ __('Biblioteca') }}</flux:heading>
        <flux:text class="text-zinc-500">
            {{ trans_choice(':count archivo en todas tus galerías|:count archivos en todas tus galerías', $this->total, ['count' => $this->total]) }}
        </flux:text>
    </div>

    <x-media-library-grid
        :media="$this->media"
        :total="$this->total"
        :empty="__('Cuando subas fotos a tus galerías, aparecerán todas aquí.')"
    />
</div>
