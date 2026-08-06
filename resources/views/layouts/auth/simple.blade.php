<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-serif:400,400i" rel="stylesheet" />

        {{-- This screen has its own light, warm-toned design regardless of the app's dark/light preference. --}}
        <script>document.documentElement.classList.remove('dark');</script>
        <style>
            [data-flux-label] { color: #3d3835 !important; }
        </style>
    </head>
    <body
        class="min-h-screen antialiased"
        style="--color-accent: #55504d; --color-accent-content: #55504d; --color-accent-foreground: #f7f1ee;"
    >
        <div class="flex min-h-svh flex-col items-center justify-center gap-8 bg-[#efe7e3] p-6 md:p-10">
            <a href="{{ route('home') }}" class="flex flex-col items-center gap-3" wire:navigate>
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-[#55504d] text-[#f7f1ee]">
                    <x-app-logo-icon class="size-6 fill-current" />
                </span>
                <span class="text-2xl text-[#3d3835]" style="font-family: 'Instrument Serif', ui-serif, serif;">
                    {{ config('app.name', 'Laravel') }}
                </span>
            </a>

            <div class="flex w-full max-w-sm flex-col gap-6 rounded-2xl border border-[#e2d6d0] bg-[#f8f3f0] p-8 shadow-[0_1px_2px_rgba(61,56,53,0.04),0_12px_28px_-10px_rgba(61,56,53,0.18)]">
                {{ $slot }}
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
