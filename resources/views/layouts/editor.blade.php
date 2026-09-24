<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    {{-- Full-screen workspace (gallery editor): no app sidebar, the page brings its own chrome. --}}
    <body class="h-dvh overflow-hidden bg-white text-zinc-800 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
