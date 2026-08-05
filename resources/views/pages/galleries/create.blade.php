<?php

use App\Concerns\GalleryValidationRules;
use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nueva galería')] class extends Component {
    use GalleryValidationRules;

    public string $title = '';
    public string $client_name = '';
    public string $unlock_code = '';
    public ?string $location = null;
    public ?string $available_until = null;

    public function save(): void
    {
        $validated = $this->validate([
            'title' => $this->titleRules(),
            'client_name' => $this->clientNameRules(),
            'unlock_code' => $this->unlockCodeRules(),
            'location' => $this->locationRules(),
            'available_until' => $this->availableUntilRules(),
        ]);

        $gallery = Auth::user()->galleries()->create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['title']),
        ]);

        session()->flash('revealed_code', $this->unlock_code);

        $this->redirect(route('galleries.edit', $gallery), navigate: true);
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'galeria';
        $slug = $base;

        while (Gallery::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}; ?>

<div class="max-w-xl">
    <flux:heading size="xl" class="mb-6">{{ __('Nueva galería') }}</flux:heading>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:input wire:model="title" :label="__('Título de la sesión')" required autofocus placeholder="Boda de Ana &amp; Marco" />
        <flux:input wire:model="client_name" :label="__('Cliente')" required placeholder="Ana Pérez" />
        <flux:input wire:model="unlock_code" :label="__('Código de desbloqueo')" required placeholder="ABC123" />

        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="available_until" :label="__('Disponible hasta')" type="date" />
            <flux:input wire:model="location" :label="__('Lugar')" placeholder="Mérida, Yuc." />
        </div>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('galleries.index')" variant="ghost" wire:navigate>{{ __('Cancelar') }}</flux:button>
            <flux:button type="submit" variant="primary" data-test="create-gallery-button">{{ __('Crear galería') }}</flux:button>
        </div>
    </form>
</div>
