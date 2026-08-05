<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

new #[Title('Historial de eventos')] class extends Component {
    use WithPagination;

    public string $logName = '';
    public string $search = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Activity>
     */
    #[Computed]
    public function activities()
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->when($this->logName !== '', fn ($query) => $query->where('log_name', $this->logName))
            ->when($this->search !== '', fn ($query) => $query->where('description', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Historial de eventos') }}</flux:heading>
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="logName" :label="__('Tipo')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            <flux:select.option value="gallery">{{ __('Galerías') }}</flux:select.option>
            <flux:select.option value="album">{{ __('Álbumes') }}</flux:select.option>
            <flux:select.option value="media">{{ __('Archivos') }}</flux:select.option>
            <flux:select.option value="user">{{ __('Cuentas') }}</flux:select.option>
        </flux:select>

        <flux:input wire:model.live.debounce.400ms="search" :label="__('Buscar en la descripción')" placeholder="Ej. boda-de-ana" class="max-w-xs" />
    </div>

    @if ($this->activities->isEmpty())
        <flux:text class="text-zinc-500">{{ __('No hay eventos registrados todavía.') }}</flux:text>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Fecha') }}</flux:table.column>
                    <flux:table.column>{{ __('Usuario') }}</flux:table.column>
                    <flux:table.column>{{ __('Tipo') }}</flux:table.column>
                    <flux:table.column>{{ __('Descripción') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->activities as $item)
                        <flux:table.row>
                            <flux:table.cell class="whitespace-nowrap text-zinc-500">
                                {{ $item->created_at->translatedFormat('d M Y, H:i') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($item->causer)
                                    {{ $item->causer->name }}
                                    <flux:text class="text-xs text-zinc-500">{{ $item->causer->email }}</flux:text>
                                @else
                                    <flux:text class="text-zinc-500">{{ __('Sistema / invitado') }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc">{{ $item->log_name ?? $item->event }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $item->description }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div>{{ $this->activities->links() }}</div>
    @endif
</div>
