<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::public')] class extends Component {
    #[Computed]
    public function photos(): array
    {
        $files = glob(public_path('fotos_home').'/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];

        $names = array_map('basename', $files);
        natsort($names);

        return array_values($names);
    }
}; ?>

<div class="flex flex-col gap-24 pb-10 sm:pb-16 {{ $this->photos ? '' : 'pt-10 sm:pt-16' }}">
    <div class="relative {{ $this->photos ? 'left-1/2 right-1/2 -mx-[50vw] w-screen' : '' }}">
        @if ($this->photos)
            <div
                x-data="{
                    interval: null,
                    loops: {{ count($this->photos) > 1 ? 2 : 1 }},
                    advance(direction) {
                        $refs.track.scrollBy({ left: direction * $refs.track.clientWidth * 0.9, behavior: 'smooth' });
                    },
                    wrap() {
                        if (this.loops < 2) return;

                        const track = $refs.track;
                        const oneSetWidth = track.scrollWidth / this.loops;

                        if (track.scrollLeft >= oneSetWidth * (this.loops - 1) - 1) {
                            track.scrollTo({ left: track.scrollLeft - oneSetWidth, behavior: 'instant' });
                        }
                    },
                    scroll(direction) {
                        this.advance(direction);
                        this.restartAutoplay();
                    },
                    startAutoplay() {
                        this.interval = setInterval(() => this.advance(1), 5000);
                    },
                    restartAutoplay() {
                        clearInterval(this.interval);
                        this.startAutoplay();
                    },
                }"
                x-init="startAutoplay()"
            >
                <div
                    x-ref="track"
                    x-on:scrollend="wrap()"
                    class="flex snap-x snap-mandatory gap-1 overflow-x-auto scroll-smooth [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                >
                    @for ($i = 0; $i < (count($this->photos) > 1 ? 2 : 1); $i++)
                        @foreach ($this->photos as $photo)
                            <img
                                src="{{ asset('fotos_home/'.rawurlencode($photo)) }}"
                                class="h-[46vh] w-auto flex-none snap-center object-cover sm:h-[60vh] lg:h-[70vh]"
                                alt=""
                                loading="lazy"
                            >
                        @endforeach
                    @endfor
                </div>

                @if (count($this->photos) > 1)
                    <button
                        type="button"
                        x-on:click="scroll(-1)"
                        class="absolute top-1/2 left-3 flex size-9 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-zinc-50/90 text-zinc-700 shadow-sm hover:bg-zinc-50 sm:left-6 dark:bg-zinc-900/80 dark:text-zinc-200 dark:hover:bg-zinc-900"
                        aria-label="{{ __('Anterior') }}"
                    >
                        <flux:icon name="chevron-left" class="size-5" />
                    </button>
                    <button
                        type="button"
                        x-on:click="scroll(1)"
                        class="absolute top-1/2 right-3 flex size-9 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-zinc-50/90 text-zinc-700 shadow-sm hover:bg-zinc-50 sm:right-6 dark:bg-zinc-900/80 dark:text-zinc-200 dark:hover:bg-zinc-900"
                        aria-label="{{ __('Siguiente') }}"
                    >
                        <flux:icon name="chevron-right" class="size-5" />
                    </button>
                @endif
            </div>
        @endif

        <section
            class="{{ $this->photos
                ? 'pointer-events-none absolute inset-0 flex items-center justify-center p-6'
                : 'mx-auto flex max-w-2xl flex-col items-center gap-6 text-center' }}"
        >
            <div
                class="{{ $this->photos
                    ? 'pointer-events-auto flex w-full max-w-md flex-col items-center gap-6 rounded-2xl border border-[#e2d6d0] bg-[#f8f3f0] p-8 text-center shadow-[0_1px_2px_rgba(61,56,53,0.04),0_12px_28px_-10px_rgba(61,56,53,0.25)] dark:border-zinc-700 dark:bg-zinc-900'
                    : 'flex flex-col items-center gap-6 text-center' }}"
            >
                <span class="text-xs font-medium tracking-[0.2em] text-zinc-500 uppercase">
                    {{ __('Portafolio de fotografías') }}
                </span>

                <h1
                    class="text-4xl leading-tight text-zinc-800 sm:text-5xl dark:text-zinc-50"
                    style="font-family: 'Instrument Serif', ui-serif, serif;"
                >
                    {{ __('Cada sesión, lista para compartir con quien tú quieras') }}
                </h1>

                <p class="max-w-lg text-lg text-zinc-500">
                    {{ __('Sube las fotos y video de tu sesión, comparte lo mejor de forma pública, y deja que tus visitantes desbloqueen todo lo demás con un código.') }}
                </p>

                <div class="flex flex-wrap items-center justify-center gap-3">
                    <flux:button :href="route('register')" variant="primary" wire:navigate>
                        {{ __('Crear cuenta') }}
                    </flux:button>
                    <flux:button :href="route('login')" variant="ghost" wire:navigate>
                        {{ __('Ya tengo cuenta') }}
                    </flux:button>
                </div>
            </div>
        </section>
    </div>

    <section class="mx-auto grid w-full max-w-4xl gap-12 border-t border-zinc-200 pt-16 sm:grid-cols-3 dark:border-zinc-700">
        <div class="flex flex-col gap-2">
            <span
                class="text-3xl text-zinc-300 dark:text-zinc-600"
                style="font-family: 'Instrument Serif', ui-serif, serif;"
            >01</span>
            <flux:heading>{{ __('Subes la sesión') }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ __('Organiza fotos y video en álbumes: preparativos, ceremonia, retratos... lo que necesite esa sesión.') }}
            </flux:text>
        </div>

        <div class="flex flex-col gap-2">
            <span
                class="text-3xl text-zinc-300 dark:text-zinc-600"
                style="font-family: 'Instrument Serif', ui-serif, serif;"
            >02</span>
            <flux:heading>{{ __('Compartes el enlace') }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ __('Tu cliente ve de inmediato las fotos destacadas, públicas y con marca de agua, sin necesidad de cuenta.') }}
            </flux:text>
        </div>

        <div class="flex flex-col gap-2">
            <span
                class="text-3xl text-zinc-300 dark:text-zinc-600"
                style="font-family: 'Instrument Serif', ui-serif, serif;"
            >03</span>
            <flux:heading>{{ __('Desbloquea con código') }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ __('Con el código que tú le compartes, tu cliente entra, ve todo el material y lo descarga en un .zip.') }}
            </flux:text>
        </div>
    </section>

    <section class="mx-auto flex w-full max-w-4xl flex-wrap justify-between gap-x-8 gap-y-6 border-t border-zinc-200 pt-10 pb-4 dark:border-zinc-700">
        <flux:text class="text-zinc-500">{{ __('Fotos y video') }}</flux:text>
        <flux:text class="text-zinc-500">{{ __('Marca de agua automática') }}</flux:text>
        <flux:text class="text-zinc-500">{{ __('Código de acceso por sesión') }}</flux:text>
        <flux:text class="text-zinc-500">{{ __('Descarga por lote en .zip') }}</flux:text>
    </section>
</div>
