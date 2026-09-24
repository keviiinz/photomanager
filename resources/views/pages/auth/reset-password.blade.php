@php
    $inputClass = 'h-13 w-full border border-[#C1B6A4] bg-white/70 px-5 text-base text-[#3d3835] placeholder:text-[#918674] transition-colors focus:border-[#B3907A] focus:bg-white focus:outline-none aria-invalid:border-[#b42318] aria-invalid:focus:border-[#b42318]';
    $errorClass = 'mt-1.5 text-sm font-medium text-[#b42318]';
    $serverErrors = collect($errors->getMessages())->map(fn (array $messages) => $messages[0]);
@endphp

<x-layouts::auth.framed :title="__('Nueva contraseña')">
    <div
        x-data="{
            errors: @js($serverErrors->isEmpty() ? new stdClass : $serverErrors),
            password: '',
            confirmation: '',
            showPassword: false,
            showConfirmation: false,
            get hasLength() { return this.password.length >= 8 },
            get hasNumber() { return /\d/.test(this.password) },
            get hasSpecial() { return /[^A-Za-z0-9\s]/.test(this.password) },
            submit(event) {
                if (! (this.hasLength && this.hasNumber && this.hasSpecial)) {
                    this.errors.password = @js(__('Tu contraseña aún no cumple los requisitos.'));
                }

                if (this.confirmation === '') {
                    this.errors.password_confirmation = @js(__('Confirma tu contraseña.'));
                } else if (this.confirmation !== this.password) {
                    this.errors.password_confirmation = @js(__('Las contraseñas no coinciden.'));
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
                {{ __('Crea una nueva contraseña') }}
            </h1>
            <p class="text-sm text-[#918674]">
                {{ __('Para la cuenta') }}
                <span class="font-medium text-[#6d6058]">{{ request('email') }}</span>
            </p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4" x-on:submit="submit($event)" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">
            <input type="hidden" name="email" value="{{ old('email', request('email')) }}">

            @error('email')
                <p class="text-center text-sm font-medium text-[#b42318]">{{ $message }}</p>
            @enderror

            <div>
                <label for="password" class="sr-only">{{ __('Nueva contraseña') }}</label>
                <div class="relative">
                    <input
                        id="password"
                        name="password"
                        type="password"
                        x-bind:type="showPassword ? 'text' : 'password'"
                        x-model="password"
                        x-on:input="errors.password = null"
                        x-bind:aria-invalid="!! errors.password"
                        autofocus
                        autocomplete="new-password"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                        placeholder="{{ __('Nueva contraseña') }}"
                        aria-describedby="password-requirements"
                        class="{{ $inputClass }} pe-13"
                    >
                    <x-password-toggle for="password" state="showPassword" />
                </div>
                <p x-show="errors.password" x-text="errors.password" class="{{ $errorClass }}" @unless ($errors->has('password')) x-cloak @endunless>{{ $errors->first('password') }}</p>

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
                        type="password"
                        x-bind:type="showConfirmation ? 'text' : 'password'"
                        x-model="confirmation"
                        x-on:input="errors.password_confirmation = null"
                        x-bind:aria-invalid="!! errors.password_confirmation"
                        autocomplete="new-password"
                        placeholder="{{ __('Confirmar contraseña') }}"
                        class="{{ $inputClass }} pe-13"
                    >
                    <x-password-toggle for="password_confirmation" state="showConfirmation" />
                </div>
                <p x-show="errors.password_confirmation" x-text="errors.password_confirmation" class="{{ $errorClass }}" x-cloak></p>
            </div>

            <button
                type="submit"
                class="mt-2 h-13 w-full cursor-pointer bg-[#B3907A] text-sm font-semibold tracking-[0.18em] text-[#F5F5EB] uppercase transition-colors hover:bg-[#9f7d68]"
                data-test="reset-password-button"
            >
                {{ __('Guardar contraseña') }}
            </button>
        </form>
    </div>
</x-layouts::auth.framed>
