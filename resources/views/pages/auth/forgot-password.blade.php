@php
    $inputClass = 'h-13 w-full border border-[#C1B6A4] bg-white/70 px-5 text-base text-[#3d3835] placeholder:text-[#918674] transition-colors focus:border-[#B3907A] focus:bg-white focus:outline-none aria-invalid:border-[#b42318] aria-invalid:focus:border-[#b42318]';
    $errorClass = 'mt-1.5 text-sm font-medium text-[#b42318]';
@endphp

<x-layouts::auth.framed :title="__('Recuperar contraseña')">
    <div
        x-data="{
            error: @js($errors->first('email') ?: null),
            submit(event) {
                const email = $refs.email;

                if (email.value.trim() === '') {
                    this.error = @js(__('Ingresa tu correo electrónico.'));
                } else if (! email.validity.valid) {
                    this.error = @js(__('Ingresa un correo electrónico válido.'));
                }

                if (this.error) {
                    event.preventDefault();
                    email.focus();
                }
            },
        }"
        class="flex flex-col gap-7"
    >
        <div class="flex flex-col gap-2 text-center">
            <h1 class="font-display text-3xl leading-tight text-balance text-[#3d3835] sm:text-[2.125rem]">
                {{ __('¿Olvidaste tu contraseña?') }}
            </h1>
            <p class="text-sm text-balance text-[#918674]">
                {{ __('Escribe tu correo y te enviaremos un enlace para crear una nueva.') }}
            </p>
        </div>

        @if (session('status'))
            <p class="border border-[#2f6b3a]/30 bg-[#2f6b3a]/8 px-4 py-3 text-center text-sm font-medium text-[#2f6b3a]">
                {{ __('Listo. Revisa tu bandeja de entrada (y la carpeta de spam) para continuar.') }}
            </p>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4" x-on:submit="submit($event)" novalidate>
            @csrf

            <div>
                <label for="email" class="sr-only">{{ __('Correo electrónico') }}</label>
                <input
                    x-ref="email"
                    id="email"
                    name="email"
                    x-on:input="error = null"
                    x-bind:aria-invalid="!! error"
                    type="email"
                    value="{{ old('email') }}"
                    autofocus
                    autocomplete="email"
                    placeholder="{{ __('tu@email.com') }}"
                    class="{{ $inputClass }}"
                >
                <p x-show="error" x-text="error" class="{{ $errorClass }}" @unless ($errors->has('email')) x-cloak @endunless>{{ $errors->first('email') }}</p>
            </div>

            <button
                type="submit"
                class="mt-2 h-13 w-full cursor-pointer bg-[#B3907A] text-sm font-semibold tracking-[0.18em] text-[#F5F5EB] uppercase transition-colors hover:bg-[#9f7d68]"
                data-test="email-password-reset-link-button"
            >
                {{ __('Enviar enlace') }}
            </button>

            <a
                href="{{ route('login') }}"
                class="flex h-13 w-full items-center justify-center bg-[#E1DACA] text-sm font-medium tracking-[0.18em] text-[#6d6058] uppercase transition-colors hover:bg-[#d6cdb9]"
                wire:navigate
            >
                {{ __('Volver a iniciar sesión') }}
            </a>
        </form>
    </div>
</x-layouts::auth.framed>
