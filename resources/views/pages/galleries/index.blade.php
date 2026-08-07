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
                @php $cover = $gallery->coverImage(); @endphp
                <a href="{{ route('galleries.edit', $gallery) }}" wire:navigate
                    class="group flex flex-col overflow-hidden rounded-xl border border-zinc-200 hover:border-accent dark:border-zinc-700">
                    <div class="aspect-[4/3] w-full overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                        @if ($cover)
                            <img
                                src="{{ route('media.show', $cover) }}"
                                alt=""
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                            >
                        @else
                            <div class="flex h-full w-full items-center justify-center">
                                <flux:icon name="photo" class="size-10 text-zinc-300 dark:text-zinc-600" />
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col gap-1 p-4">
                        <flux:heading>{{ $gallery->title }}</flux:heading>
                        <flux:text>{{ $gallery->client_name }}</flux:text>
                        <flux:text class="text-sm text-zinc-500">
                            {{ trans_choice(':count álbum|:count álbumes', $gallery->albums_count, ['count' => $gallery->albums_count]) }}
                            ·
                            {{ trans_choice(':count archivo|:count archivos', $gallery->media_count, ['count' => $gallery->media_count]) }}
                        </flux:text>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
