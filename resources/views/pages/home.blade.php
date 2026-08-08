<?php

use App\Models\HomeImage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::public')] class extends Component {
    /**
     * @return \Illuminate\Support\Collection<int, HomeImage>
     */
    #[Computed]
    public function images()
    {
        return HomeImage::orderBy('position')->get();
    }

    #[Computed]
    public function primaryImage(): ?HomeImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }
}; ?>

<div x-data="{ introOpen: true }" x-effect="document.body.style.overflow = introOpen ? 'hidden' : ''">
    <div
        x-show="introOpen"
        x-transition:leave="transition ease-in-out duration-700"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900"
    >
        @if ($this->primaryImage)
            <img
                src="{{ route('home-images.show', $this->primaryImage) }}"
                class="absolute inset-0 h-full w-full object-cover"
                alt=""
            >
        @endif
        <div class="absolute inset-0 bg-black/55"></div>

        <div class="relative flex max-w-xl flex-col items-center gap-6 p-6 text-center text-white">
            <span class="text-xs font-medium tracking-[0.2em] text-white/70 uppercase">
                {{ __('Portafolio de fotografías') }}
            </span>

            <h1
                class="text-4xl leading-tight sm:text-5xl"
                style="font-family: 'Instrument Serif', ui-serif, serif;"
            >
                {{ __('Cada sesión, lista para compartir con quien tú quieras') }}
            </h1>

            <p class="max-w-lg text-lg text-white/80">
                {{ __('Sube las fotos y video de tu sesión, comparte lo mejor de forma pública, y deja que tus visitantes desbloqueen todo lo demás con un código.') }}
            </p>

            <flux:button type="button" variant="primary" x-on:click="introOpen = false">
                {{ __('Ir a la página') }}
            </flux:button>
        </div>
    </div>

    <div
        x-bind:class="introOpen ? 'opacity-0' : 'opacity-100'"
        class="flex flex-col gap-24 pb-10 sm:pb-16 transition-opacity duration-700 {{ $this->images->isNotEmpty() ? '' : 'pt-10 sm:pt-16' }}"
    >
    <div class="relative {{ $this->images->isNotEmpty() ? 'left-1/2 right-1/2 -mx-[50vw] w-screen' : '' }}">
        @if ($this->images->isNotEmpty())
            <div
                x-data="{
                    interval: null,
                    loops: {{ $this->images->count() > 1 ? 2 : 1 }},
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
                    @for ($i = 0; $i < ($this->images->count() > 1 ? 2 : 1); $i++)
                        @foreach ($this->images as $image)
                            <img
                                src="{{ route('home-images.show', $image) }}"
                                class="h-[46vh] w-auto flex-none snap-center object-cover sm:h-[60vh] lg:h-[70vh]"
                                alt=""
                                loading="lazy"
                            >
                        @endforeach
                    @endfor
                </div>

                @if ($this->images->count() > 1)
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
                {{ __('Tu cliente ve de inmediato las fotos destacadas, públicas y en su calidad original, sin necesidad de cuenta.') }}
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
        <flux:text class="text-zinc-500">{{ __('Vista previa de las fotos destacadas') }}</flux:text>
        <flux:text class="text-zinc-500">{{ __('Código de acceso por sesión') }}</flux:text>
        <flux:text class="text-zinc-500">{{ __('Descarga por lote en .zip') }}</flux:text>
    </section>
    </div>
</div>
