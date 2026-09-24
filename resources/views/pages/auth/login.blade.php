@php
    $inputClass = 'h-13 w-full border border-[#C1B6A4] bg-white/70 px-5 text-base text-[#3d3835] placeholder:text-[#918674] transition-colors focus:border-[#B3907A] focus:bg-white focus:outline-none aria-invalid:border-[#b42318] aria-invalid:focus:border-[#b42318]';
    $errorClass = 'mt-1.5 text-sm font-medium text-[#b42318]';
    $serverErrors = collect($errors->getMessages())->map(fn (array $messages) => $messages[0]);
@endphp

<x-layouts::auth.framed :title="__('Iniciar sesión')">
    <div
        x-data="{
            errors: @js($serverErrors->isEmpty() ? new stdClass : $serverErrors),
            visible: false,
            submit(event) {
                const email = $refs.email;

                if (email.value.trim() === '') {
                    this.errors.email = @js(__('Ingresa tu correo electrónico.'));
                } else if (! email.validity.valid) {
                    this.errors.email = @js(__('Ingresa un correo electrónico válido.'));
                }

                if ($refs.password.value === '') {
                    this.errors.password = @js(__('Ingresa tu contraseña.'));
                }

                if (Object.values(this.errors).some((error) => error)) {
                    event.preventDefault();
                    this.$nextTick(() => $root.querySelector('[aria-invalid=true]')?.focus());
                }
            },
        }"
        class="flex flex-col gap-7"
    >
        <div class="flex flex-col gap-2 text-center">
            <h1 class="font-display text-3xl leading-tight text-balance text-[#3d3835] sm:text-[2.125rem]">
                {{ __('Inicia sesión en tu cuenta') }}
            </h1>
            @if (Route::has('register'))
                <p class="text-sm text-[#918674]">
                    {{ __('¿Aún no tienes una cuenta?') }}
                    <a href="{{ route('register') }}" class="font-medium text-[#8a6a57] underline-offset-4 hover:underline" wire:navigate>
                        {{ __('Crear cuenta') }}
                    </a>
                </p>
            @endif
        </div>

        @if (session('status'))
            <p class="border border-[#2f6b3a]/30 bg-[#2f6b3a]/8 px-4 py-3 text-center text-sm font-medium text-[#2f6b3a]">
                {{ session('status') }}
            </p>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4" x-on:submit="submit($event)" novalidate>
            @csrf

            <div>
                <label for="email" class="sr-only">{{ __('Correo electrónico') }}</label>
                <input
                    x-ref="email"
                    id="email"
                    name="email"
                    x-on:input="errors.email = null"
                    x-bind:aria-invalid="!! errors.email"
                    type="email"
                    value="{{ old('email') }}"
                    autofocus
                    autocomplete="email"
                    placeholder="{{ __('tu@email.com') }}"
                    class="{{ $inputClass }}"
                >
                <p x-show="errors.email" x-text="errors.email" class="{{ $errorClass }}" @if (! $errors->has('email')) x-cloak @endif>{{ $errors->first('email') }}</p>
            </div>

            <div>
                <label for="password" class="sr-only">{{ __('Contraseña') }}</label>
                <div class="relative">
                    <input
                        x-ref="password"
                        id="password"
                        name="password"
                        x-on:input="errors.password = null"
                        x-bind:aria-invalid="!! errors.password"
                        type="password"
                        x-bind:type="visible ? 'text' : 'password'"
                        autocomplete="current-password"
                        placeholder="{{ __('Contraseña') }}"
                        class="{{ $inputClass }} pe-13"
                    >
                    <x-password-toggle for="password" state="visible" />
                </div>
                <p x-show="errors.password" x-text="errors.password" class="{{ $errorClass }}" @if (! $errors->has('password')) x-cloak @endif>{{ $errors->first('password') }}</p>
            </div>

            <div class="flex items-center justify-between gap-4 text-sm">
                <label class="flex cursor-pointer items-center gap-2 text-[#6d6058]">
                    <input
                        type="checkbox"
                        name="remember"
                        @checked(old('remember'))
                        class="size-4 cursor-pointer accent-[#B3907A]"
                    >
                    {{ __('Recordarme') }}
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="font-medium text-[#8a6a57] underline-offset-4 hover:underline" wire:navigate>
                        {{ __('¿Olvidaste tu contraseña?') }}
                    </a>
                @endif
            </div>

            <button
                type="submit"
                class="mt-2 h-13 w-full cursor-pointer bg-[#B3907A] text-sm font-semibold tracking-[0.18em] text-[#F5F5EB] uppercase transition-colors hover:bg-[#9f7d68]"
                data-test="login-button"
            >
                {{ __('Iniciar sesión') }}
            </button>
        </form>
    </div>
</x-layouts::auth.framed>
