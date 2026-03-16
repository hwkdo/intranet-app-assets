<?php

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    public bool $showForm = false;

    #[Computed]
    public function assetVendors(): \Illuminate\Database\Eloquent\Collection
    {
        return AssetVendor::orderBy('name')->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $vendor = AssetVendor::findOrFail($id);
        $this->editingId = $id;
        $this->name = $vendor->name;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            AssetVendor::findOrFail($this->editingId)->update(['name' => $this->name]);
            Flux::toast('Hersteller wurde aktualisiert.', variant: 'success');
        } else {
            AssetVendor::create(['name' => $this->name]);
            Flux::toast('Hersteller wurde erstellt.', variant: 'success');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $vendor = AssetVendor::findOrFail($id);

        if ($vendor->assets()->exists()) {
            Flux::toast('Hersteller kann nicht gelöscht werden, da noch Assets zugeordnet sind.', variant: 'danger');
            return;
        }

        $vendor->delete();
        Flux::toast('Hersteller wurde gelöscht.', variant: 'success');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->showForm = false;
        $this->resetValidation();
    }
};
?>

<div class="space-y-4">
    @if($showForm)
        <flux:card class="space-y-4">
            <flux:heading size="lg">
                {{ $editingId ? 'Hersteller bearbeiten' : 'Neuer Hersteller' }}
            </flux:heading>

            <form wire:submit="save" class="space-y-4">
                <flux:field>
                    <flux:label>Name <flux:badge size="sm" color="red">Pflicht</flux:badge></flux:label>
                    <flux:input wire:model="name" placeholder="z.B. Lenovo" autofocus />
                    <flux:error name="name" />
                </flux:field>

                <div class="flex gap-3">
                    <flux:button type="submit" variant="primary" size="sm">
                        {{ $editingId ? 'Speichern' : 'Erstellen' }}
                    </flux:button>
                    <flux:button wire:click="cancel" variant="ghost" size="sm">Abbrechen</flux:button>
                </div>
            </form>
        </flux:card>
    @else
        <div class="flex justify-end">
            <flux:button wire:click="create" variant="primary" icon="plus" size="sm">
                Neuer Hersteller
            </flux:button>
        </div>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Assets</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->assetVendors as $vendor)
                <flux:table.row wire:key="{{ $vendor->id }}">
                    <flux:table.cell class="font-medium">{{ $vendor->name }}</flux:table.cell>
                    <flux:table.cell>{{ $vendor->assets()->count() }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:button wire:click="edit({{ $vendor->id }})" variant="ghost" size="sm" icon="pencil" />
                            <flux:button
                                wire:click="delete({{ $vendor->id }})"
                                wire:confirm="Hersteller '{{ $vendor->name }}' wirklich löschen?"
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                class="text-red-500 hover:text-red-700"
                            />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" class="text-center text-zinc-500 py-6">
                        Noch keine Hersteller vorhanden.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
