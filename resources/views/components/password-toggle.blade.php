@props([
    // id of the password input this button controls.
    'for',
    // Alpine boolean (in the surrounding x-data) that holds the visible state.
    'state',
])

{{-- Eye button that shows/hides a password input; place it inside a `relative` wrapper next to the input. --}}
<button
    type="button"
    x-on:click="{{ $state }} = ! {{ $state }}; document.getElementById(@js($for)).focus()"
    x-bind:aria-label="{{ $state }} ? @js(__('Ocultar contraseña')) : @js(__('Mostrar contraseña'))"
    x-bind:aria-pressed="{{ $state }}"
    aria-label="{{ __('Mostrar contraseña') }}"
    aria-controls="{{ $for }}"
    class="absolute inset-y-0 end-0 flex w-13 cursor-pointer items-center justify-center text-[#918674] transition-colors hover:text-[#3d3835] focus-visible:text-[#3d3835] focus-visible:outline-none"
>
    <flux:icon.eye variant="outline" class="size-5" x-show="! {{ $state }}" />
    <flux:icon.eye-slash variant="outline" class="size-5" x-show="{{ $state }}" x-cloak />
</button>
