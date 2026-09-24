@php
    $inputClass = 'h-13 w-full border border-[#C1B6A4] bg-white/70 px-5 text-base text-[#3d3835] placeholder:text-[#918674] transition-colors focus:border-[#B3907A] focus:bg-white focus:outline-none aria-invalid:border-[#b42318] aria-invalid:focus:border-[#b42318]';
    $errorClass = 'mt-1.5 text-sm font-medium text-[#b42318]';
    $serverErrors = collect($errors->getMessages())->map(fn (array $messages) => $messages[0]);
@endphp

<x-layouts::auth.framed :title="__('Crear cuenta')">
    <div
        x-data="{
            step: {{ $errors->any() ? 2 : 1 }},
            errors: @js($serverErrors->isEmpty() ? new stdClass : $serverErrors),
            password: '',
            confirmation: '',
            showPassword: false,
            showConfirmation: false,
            get hasLength() { return this.password.length >= 8 },
            get hasNumber() { return /\d/.test(this.password) },
            get hasSpecial() { return /[^A-Za-z0-9\s]/.test(this.password) },
            validateEmail() {
                const email = $refs.email;

                if (email.value.trim() === '') {
                    this.errors.email = @js(__('Ingresa tu correo electrónico.'));
                } else if (! email.validity.valid) {
                    this.errors.email = @js(__('Ingresa un correo electrónico válido.'));
                }

                return ! this.errors.email;
            },
            continueWithEmail() {
                if (! this.validateEmail()) return $refs.email.focus();

                this.step = 2;
                this.$nextTick(() => $refs.name.focus());
            },
            validateAll() {
                this.validateEmail();

                if ($refs.name.value.trim() === '') {
                    this.errors.name = @js(__('Ingresa tu nombre.'));
                }

                if (! (this.hasLength && this.hasNumber && this.hasSpecial)) {
                    this.errors.password = @js(__('Tu contraseña aún no cumple los requisitos.'));
                }

                if (this.confirmation === '') {
                    this.errors.password_confirmation = @js(__('Confirma tu contraseña.'));
                } else if (this.confirmation !== this.password) {
                    this.errors.password_confirmation = @js(__('Las contraseñas no coinciden.'));
                }

                this.$nextTick(() => $root.querySelector('[aria-invalid=true]')?.focus());

                return Object.values(this.errors).every((error) => ! error);
            },
            submit(event) {
                if (this.step === 1) {
                    event.preventDefault();

                    return this.continueWithEmail();
                }

                if (! this.validateAll()) event.preventDefault();
            },
        }"
        class="flex flex-col gap-7"
    >
        <div class="flex flex-col gap-2 text-center">
            <h1 class="font-display text-3xl leading-tight text-balance text-[#3d3835] sm:text-[2.125rem]">
                <span x-show="step === 1">{{ __('Crea tu cuenta') }}</span>
                <span x-show="step === 2" x-cloak>{{ __('Termina de configurar tu cuenta') }}</span>
            </h1>
            <p class="text-sm text-[#918674]">
                {{ __('¿Ya tienes una cuenta?') }}
                <a href="{{ route('login') }}" class="font-medium text-[#8a6a57] underline-offset-4 hover:underline" wire:navigate>
                    {{ __('Iniciar sesión') }}
                </a>
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('register.store') }}"
            class="flex flex-col gap-4"
            x-on:submit="submit($event)"
            novalidate
        >
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
                    @unless ($errors->any()) autofocus @endunless
                    autocomplete="email"
                    placeholder="{{ __('tu@email.com') }}"
                    class="{{ $inputClass }}"
                >
                <p x-show="errors.email" x-text="errors.email" id="email-error" class="{{ $errorClass }}" @if (! $errors->has('email')) x-cloak @endif>{{ $errors->first('email') }}</p>
            </div>

            <div x-show="step === 1">
                <button
                    type="button"
                    x-on:click="continueWithEmail()"
                    class="h-13 w-full cursor-pointer bg-[#B3907A] text-sm font-semibold tracking-[0.18em] text-[#F5F5EB] uppercase transition-colors hover:bg-[#9f7d68]"
                >
                    {{ __('Continuar con email') }}
                </button>
            </div>

            <div
                x-show="step === 2"
                x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="flex flex-col gap-4"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="sr-only">{{ __('Nombre') }}</label>
                        <input
                            x-ref="name"
                            id="name"
                            name="name"
                            x-on:input="errors.name = null"
                            x-bind:aria-invalid="!! errors.name"
                            type="text"
                            value="{{ old('name') }}"
                            autocomplete="name"
                            placeholder="{{ __('Nombre') }}"
                            class="{{ $inputClass }}"
                        >
                        <p x-show="errors.name" x-text="errors.name" id="name-error" class="{{ $errorClass }}" @if (! $errors->has('name')) x-cloak @endif>{{ $errors->first('name') }}</p>
                    </div>

                    <div>
                        <label for="company_name" class="sr-only">{{ __('Nombre de tu empresa') }}</label>
                        <input
                            id="company_name"
                            name="company_name"
                            x-on:input="errors.company_name = null"
                            x-bind:aria-invalid="!! errors.company_name"
                            type="text"
                            value="{{ old('company_name') }}"
                            autocomplete="organization"
                            placeholder="{{ __('Empresa (opcional)') }}"
                            class="{{ $inputClass }}"
                        >
                        <p x-show="errors.company_name" x-text="errors.company_name" id="company_name-error" class="{{ $errorClass }}" @if (! $errors->has('company_name')) x-cloak @endif>{{ $errors->first('company_name') }}</p>
                    </div>
                </div>

                <div>
                    <label for="password" class="sr-only">{{ __('Contraseña') }}</label>
                    <div class="relative">
                        <input
                            id="password"
                            name="password"
                            x-on:input="errors.password = null"
                            x-bind:aria-invalid="!! errors.password"
                            type="password"
                            x-bind:type="showPassword ? 'text' : 'password'"
                            x-model="password"
                            autocomplete="new-password"
                            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                            placeholder="{{ __('Contraseña') }}"
                            aria-describedby="password-requirements"
                            class="{{ $inputClass }} pe-13"
                        >
                        <x-password-toggle for="password" state="showPassword" />
                    </div>
                    <p x-show="errors.password" x-text="errors.password" id="password-error" class="{{ $errorClass }}" @if (! $errors->has('password')) x-cloak @endif>{{ $errors->first('password') }}</p>

                    <ul id="password-requirements" class="mt-2.5 flex flex-wrap gap-x-5 gap-y-1.5 text-[13px] font-medium text-[#6d6058]">
                        @foreach ([
                            'hasLength' => __('8 caracteres'),
                            'hasNumber' => __('Un número'),
                            'hasSpecial' => __('Un carácter especial'),
                        ] as $check => $label)
                            <li class="flex items-center gap-1.5 transition-colors" x-bind:class="{{ $check }} && 'text-[#2f6b3a]'">
                                <span
                                    class="flex size-4 items-center justify-center rounded-full border-[1.5px] border-[#918674] transition-colors"
                                    x-bind:class="{{ $check }} && '!border-[#2f6b3a] bg-[#2f6b3a] text-white'"
                                >
                                    <flux:icon.check variant="micro" class="size-3" x-show="{{ $check }}" x-cloak />
                                </span>
                                {{ $label }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <label for="password_confirmation" class="sr-only">{{ __('Confirmar contraseña') }}</label>
                    <div class="relative">
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            x-on:input="errors.password_confirmation = null"
                            x-bind:aria-invalid="!! errors.password_confirmation"
                            type="password"
                            x-bind:type="showConfirmation ? 'text' : 'password'"
                            x-model="confirmation"
                            autocomplete="new-password"
                            placeholder="{{ __('Confirmar contraseña') }}"
                            class="{{ $inputClass }} pe-13"
                        >
                        <x-password-toggle for="password_confirmation" state="showConfirmation" />
                    </div>
                    <p x-show="errors.password_confirmation" x-text="errors.password_confirmation" id="password_confirmation-error" class="{{ $errorClass }}" x-cloak></p>
                </div>

                <button
                    type="submit"
                    class="mt-2 h-13 w-full cursor-pointer bg-[#B3907A] text-sm font-semibold tracking-[0.18em] text-[#F5F5EB] uppercase transition-colors hover:bg-[#9f7d68]"
                    data-test="register-user-button"
                >
                    {{ __('Crear cuenta') }}
                </button>

                <button
                    type="button"
                    x-on:click="step = 1; $nextTick(() => $refs.email.focus())"
                    class="h-13 w-full cursor-pointer bg-[#E1DACA] text-sm font-medium tracking-[0.18em] text-[#6d6058] uppercase transition-colors hover:bg-[#d6cdb9]"
                >
                    {{ __('Atrás') }}
                </button>
            </div>
        </form>
    </div>
</x-layouts::auth.framed>
