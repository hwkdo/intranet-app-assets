<?php

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    public bool $is_domain_object = false;

    public bool $is_intune_object = false;

    public bool $showForm = false;

    #[Computed]
    public function assetTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return AssetType::orderBy('name')->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $type = AssetType::findOrFail($id);
        $this->editingId = $id;
        $this->name = $type->name;
        $this->is_domain_object = $type->is_domain_object;
        $this->is_intune_object = $type->is_intune_object;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            AssetType::findOrFail($this->editingId)->update([
                'name' => $this->name,
                'is_domain_object' => $this->is_domain_object,
                'is_intune_object' => $this->is_intune_object,
            ]);
            Flux::toast('Typ wurde aktualisiert.', variant: 'success');
        } else {
            AssetType::create([
                'name' => $this->name,
                'is_domain_object' => $this->is_domain_object,
                'is_intune_object' => $this->is_intune_object,
            ]);
            Flux::toast('Typ wurde erstellt.', variant: 'success');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $type = AssetType::findOrFail($id);

        if ($type->assets()->exists()) {
            Flux::toast('Typ kann nicht gelöscht werden, da noch Assets zugeordnet sind.', variant: 'danger');
            return;
        }

        $type->delete();
        Flux::toast('Typ wurde gelöscht.', variant: 'success');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->is_domain_object = false;
        $this->is_intune_object = false;
        $this->showForm = false;
        $this->resetValidation();
    }
};
?>

<div class="space-y-4">
    @if($showForm)
        <flux:card class="space-y-4">
            <flux:heading size="lg">
                {{ $editingId ? 'Typ bearbeiten' : 'Neuer Typ' }}
            </flux:heading>

            <form wire:submit="save" class="space-y-4">
                <flux:field>
                    <flux:label>Name <flux:badge size="sm" color="red">Pflicht</flux:badge></flux:label>
                    <flux:input wire:model="name" placeholder="z.B. Laptop" autofocus />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="is_domain_object" label="Domain-Objekt (wird per LDAP synchronisiert)" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="is_intune_object" label="Intune-Objekt (wird mit Microsoft Intune verwaltet)" />
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
                Neuer Typ
            </flux:button>
        </div>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Domain-Objekt</flux:table.column>
            <flux:table.column>Intune-Objekt</flux:table.column>
            <flux:table.column>Assets</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->assetTypes as $type)
                <flux:table.row wire:key="{{ $type->id }}">
                    <flux:table.cell class="font-medium">{{ $type->name }}</flux:table.cell>
                    <flux:table.cell>
                        @if($type->is_domain_object)
                            <flux:badge color="blue" size="sm" icon="check">Ja</flux:badge>
                        @else
                            <flux:text class="text-zinc-400">Nein</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($type->is_intune_object)
                            <flux:badge color="sky" size="sm" icon="check">Ja</flux:badge>
                        @else
                            <flux:text class="text-zinc-400">Nein</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $type->assets_count ?? $type->assets()->count() }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:button wire:click="edit({{ $type->id }})" variant="ghost" size="sm" icon="pencil" />
                            <flux:button
                                wire:click="delete({{ $type->id }})"
                                wire:confirm="Typ '{{ $type->name }}' wirklich löschen?"
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
                    <flux:table.cell colspan="5" class="text-center text-zinc-500 py-6">
                        Noch keine Typen vorhanden.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
