<?php

use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Galerías')] class extends Component {
    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** 'grid' or 'list', remembered for the rest of the session. */
    #[Session]
    public string $view = 'grid';

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['grid', 'list'], true) ? $view : 'grid';
    }

    /**
     * @return \Illuminate\Support\Collection<int, Gallery>
     */
    #[Computed]
    public function galleries()
    {
        $search = trim($this->search);

        return Auth::user()->galleries()
            ->with('coverMedia')
            ->withCount('media')
            ->when($search !== '', fn ($query) => $query->whereAny(['title', 'client_name'], 'like', "%{$search}%"))
            ->latest()
            ->get();
    }

    #[Computed]
    public function hasAnyGallery(): bool
    {
        return trim($this->search) !== '' ? Auth::user()->galleries()->exists() : $this->galleries->isNotEmpty();
    }
}; ?>

<div class="flex flex-col gap-6">
    {{-- Header: title, search, new gallery --}}
    <div class="flex flex-wrap items-center gap-x-8 gap-y-4">
        <flux:heading size="xl" class="font-display !text-3xl !font-normal">{{ __('Galerías') }}</flux:heading>

        <div class="order-last w-full sm:order-none sm:w-auto sm:flex-1">
            <label for="gallery-search" class="sr-only">{{ __('Buscar') }}</label>
            <div class="relative max-w-xs">
                <flux:icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-0 size-5 -translate-y-1/2 text-zinc-400" />
                <input
                    id="gallery-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Buscar') }}"
                    class="h-10 w-full border-0 border-b border-transparent bg-transparent ps-8 text-base text-zinc-800 placeholder:text-zinc-400 transition-colors focus:border-zinc-300 focus:outline-none dark:text-zinc-100 dark:focus:border-zinc-600"
                >
            </div>
        </div>

        <flux:button :href="route('galleries.create')" variant="primary" icon="plus" class="ms-auto sm:ms-0" wire:navigate>
            {{ __('Nueva galería') }}
        </flux:button>
    </div>

    @if ($this->hasAnyGallery)
        {{-- Toolbar: layout toggle --}}
        <div class="flex items-center justify-between gap-4">
            <flux:text class="text-sm text-zinc-500">
                {{ trans_choice(':count galería|:count galerías', $this->galleries->count(), ['count' => $this->galleries->count()]) }}
            </flux:text>

            <div class="flex items-center gap-1" role="group" aria-label="{{ __('Vista') }}">
                @foreach (['grid' => ['squares-2x2', __('Cuadrícula')], 'list' => ['list-bullet', __('Lista')]] as $mode => [$icon, $label])
                    <button
                        type="button"
                        wire:click="setView('{{ $mode }}')"
                        aria-pressed="{{ $view === $mode ? 'true' : 'false' }}"
                        title="{{ $label }}"
                        @class([
                            'flex size-9 cursor-pointer items-center justify-center rounded-lg transition-colors',
                            'bg-zinc-200/70 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-100' => $view === $mode,
                            'text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200' => $view !== $mode,
                        ])
                    >
                        <flux:icon :name="$icon" class="size-5" />
                        <span class="sr-only">{{ $label }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if (! $this->hasAnyGallery)
        <div class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-zinc-300 px-6 py-20 text-center dark:border-zinc-700">
            <flux:icon name="photo" class="size-10 text-zinc-300 dark:text-zinc-600" />
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">{{ __('Aún no has creado ninguna galería') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Crea tu primera galería para subir fotos y compartirlas con tus clientes.') }}</flux:text>
            </div>
            <flux:button :href="route('galleries.create')" variant="primary" icon="plus" wire:navigate>
                {{ __('Nueva galería') }}
            </flux:button>
        </div>
    @elseif ($this->galleries->isEmpty())
        <flux:text class="py-10 text-center text-zinc-500">
            {{ __('No hay galerías que coincidan con “:search”.', ['search' => $search]) }}
        </flux:text>
    @elseif ($view === 'list')
        <div class="flex flex-col divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->galleries as $gallery)
                @php
                    $cover = $gallery->coverImage();
                    $state = ! $gallery->isPublished() ? 'draft' : ($gallery->isExpired() ? 'expired' : 'published');
                    $stateLabel = ['draft' => __('Borrador'), 'published' => __('Publicada'), 'expired' => __('Expirada')][$state];
                    $stateDot = ['draft' => 'border border-zinc-400', 'published' => 'bg-[#2f6b3a]', 'expired' => 'bg-zinc-400'][$state];
                @endphp
                <a
                    href="{{ route('galleries.edit', $gallery) }}"
                    wire:navigate
                    wire:key="gallery-row-{{ $gallery->id }}"
                    class="group grid grid-cols-[4.5rem_1fr_auto] items-center gap-4 py-3 transition-colors hover:bg-zinc-100/70 sm:grid-cols-[5.5rem_1fr_8rem_8rem_7rem] sm:px-2 dark:hover:bg-zinc-800/60"
                >
                    <div class="aspect-[4/3] overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                        @if ($cover)
                            <img src="{{ route('media.show', $cover) }}" alt="" loading="lazy" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center">
                                <flux:icon name="photo" class="size-5 text-zinc-300 dark:text-zinc-600" />
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <p class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $gallery->title }}</p>
                        <p class="truncate text-sm text-zinc-500">{{ $gallery->client_name }}</p>
                    </div>

                    <p class="hidden text-sm text-zinc-500 sm:block">
                        {{ trans_choice(':count elemento|:count elementos', $gallery->media_count, ['count' => $gallery->media_count]) }}
                    </p>

                    <p class="hidden text-sm text-zinc-500 sm:block">{{ $gallery->created_at->translatedFormat('j M Y') }}</p>

                    <p class="flex items-center justify-end gap-2 text-sm text-zinc-500 sm:justify-start">
                        <span class="size-2 rounded-full {{ $stateDot }}"></span>
                        {{ $stateLabel }}
                    </p>
                </a>
            @endforeach
        </div>
    @else
        <div class="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            @foreach ($this->galleries as $gallery)
                @php
                    $cover = $gallery->coverImage();
                    $state = ! $gallery->isPublished() ? 'draft' : ($gallery->isExpired() ? 'expired' : 'published');
                    $stateLabel = ['draft' => __('Borrador'), 'published' => __('Publicada'), 'expired' => __('Expirada')][$state];
                    $stateDot = ['draft' => 'border border-zinc-400', 'published' => 'bg-[#2f6b3a]', 'expired' => 'bg-zinc-400'][$state];
                @endphp
                <a href="{{ route('galleries.edit', $gallery) }}" wire:navigate wire:key="gallery-card-{{ $gallery->id }}" class="group flex flex-col gap-3">
                    <div class="aspect-[4/3] w-full overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                        @if ($cover)
                            <img
                                src="{{ route('media.show', $cover) }}"
                                alt=""
                                loading="lazy"
                                class="size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                            >
                        @else
                            <div class="flex size-full items-center justify-center">
                                <flux:icon name="photo" class="size-10 text-zinc-300 dark:text-zinc-600" />
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col gap-1">
                        <p class="truncate text-lg text-zinc-800 dark:text-zinc-100">{{ $gallery->title }}</p>
                        <p class="flex flex-wrap items-center gap-x-2 text-sm text-zinc-500">
                            <span
                                class="size-2 rounded-full {{ $stateDot }}"
                                title="{{ $stateLabel }}"
                            ></span>
                            <span @class(['text-zinc-600 dark:text-zinc-300' => $state === 'draft', 'sr-only' => $state !== 'draft'])>{{ $stateLabel }}</span>
                            @if ($state === 'draft') <span aria-hidden="true">·</span> @endif
                            {{ trans_choice(':count elemento|:count elementos', $gallery->media_count, ['count' => $gallery->media_count]) }}
                            <span aria-hidden="true">·</span>
                            {{ $gallery->created_at->translatedFormat('j M Y') }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
