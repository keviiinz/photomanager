<?php

use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Galerías')] class extends Component {
    /**
     * @return \Illuminate\Support\Collection<int, Gallery>
     */
    #[Computed]
    public function galleries()
    {
        return Auth::user()->galleries()->withCount(['albums', 'media'])->latest()->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Galerías') }}</flux:heading>
        <flux:button :href="route('galleries.create')" variant="primary" icon="plus" wire:navigate>
            {{ __('Nueva galería') }}
        </flux:button>
    </div>

    @if ($this->galleries->isEmpty())
        <flux:text class="text-zinc-500">{{ __('Aún no has creado ninguna galería.') }}</flux:text>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->galleries as $gallery)
                <a href="{{ route('galleries.edit', $gallery) }}" wire:navigate
                    class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 hover:border-accent dark:border-zinc-700">
                    <flux:heading>{{ $gallery->title }}</flux:heading>
                    <flux:text>{{ $gallery->client_name }}</flux:text>
                    <flux:text class="text-sm text-zinc-500">
                        {{ trans_choice(':count álbum|:count álbumes', $gallery->albums_count, ['count' => $gallery->albums_count]) }}
                        ·
                        {{ trans_choice(':count archivo|:count archivos', $gallery->media_count, ['count' => $gallery->media_count]) }}
                    </flux:text>
                </a>
            @endforeach
        </div>
    @endif
</div>
