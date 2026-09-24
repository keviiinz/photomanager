@php
    $backgroundImage = \App\Models\HomeImage::orderByDesc('is_primary')->orderBy('position')->first();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')

        {{-- Auth screens keep their own light, warm palette regardless of the app's dark/light preference. --}}
        <script>document.documentElement.classList.remove('dark');</script>
    </head>
    <body class="h-svh overflow-hidden bg-[#EFE7DA] text-[#3d3835] antialiased">
        {{-- Pale pink frame → photo box → centered form card. --}}
        <div class="flex h-svh p-3 sm:p-6 lg:p-10">
            <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden bg-[#E1DACA]">
                @if ($backgroundImage)
                    <img
                        src="{{ route('home-images.show', $backgroundImage) }}"
                        alt=""
                        class="absolute inset-0 size-full object-cover"
                    >
                    <div class="absolute inset-0 bg-linear-to-b from-[#3d3835]/45 via-[#3d3835]/15 to-[#3d3835]/35"></div>
                @endif

                <div class="relative z-10 flex shrink-0 items-center px-5 py-4 sm:px-8 sm:py-6 {{ $backgroundImage ? 'text-[#F5F5EB]' : 'text-[#3d3835]' }}">
                    <a
                        href="{{ route('home') }}"
                        class="font-display text-2xl font-medium sm:text-3xl"
                        wire:navigate
                    >
                        {{ config('app.name', 'PhotoManager') }}
                    </a>
                </div>

                {{-- The photo box always fills the viewport; if the card outgrows it, the card area scrolls instead of the page. --}}
                <div class="relative z-10 flex min-h-0 flex-1 justify-center overflow-y-auto px-4 pb-6 [scrollbar-width:thin] sm:px-8 sm:pb-10">
                    <div class="my-auto h-fit w-full max-w-[600px] bg-[#F5F5EB]/95 px-6 py-9 shadow-[0_30px_60px_-20px_rgba(42,37,35,0.45)] backdrop-blur-sm sm:px-12 sm:py-10">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
