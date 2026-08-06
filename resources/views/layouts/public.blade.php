<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <x-app-logo href="{{ route('home') }}" wire:navigate />

            <flux:spacer />

            @auth
                <flux:button :href="auth()->user()->isPhotographer() ? route('galleries.index') : route('galleries.my')" wire:navigate>
                    {{ __('Ir a mi panel') }}
                </flux:button>
            @else
                <flux:button :href="route('login')" wire:navigate>{{ __('Iniciar sesión') }}</flux:button>
                <flux:button :href="route('register')" variant="primary" wire:navigate>{{ __('Registrarme') }}</flux:button>
            @endauth
        </flux:header>

        <flux:main container>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
