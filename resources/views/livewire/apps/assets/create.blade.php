<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Neues Asset')] class extends Component {
    /** Allgemeine Felder (für alle Assets) */
    public string $model = '';

    public string $asset_type_id = '';

    public string $asset_vendor_id = '';

    /** Einheiten: pro Eintrag ein Asset mit spezifischen Feldern */
    public array $units = [];

    public function mount(): void
    {
        $this->units = [$this->defaultUnit()];
    }

    protected function defaultUnit(): array
    {
        return [
            'serial_number' => '',
            'name' => null,
            'location' => null,
            'user_id' => null,
            'itexia_id' => null,
            'order_number' => null,
            'invoice_number' => null,
            'domain_connection' => null,
            'intune_device_id' => null,
            'is_clarification' => false,
            'is_missing' => false,
        ];
    }

    public function addUnit(): void
    {
        $this->units[] = $this->defaultUnit();
    }

    public function removeUnit(int $index): void
    {
        if (count($this->units) <= 1) {
            return;
        }
        array_splice($this->units, $index, 1);
    }

    #[Computed]
    public function showDomainConnectionField(): bool
    {
        $type = AssetType::find($this->asset_type_id);

        return $type?->is_domain_object ?? false;
    }

    #[Computed]
    public function showIntuneObjectField(): bool
    {
        $type = AssetType::find($this->asset_type_id);

        return $type?->is_intune_object ?? false;
    }

    #[Computed]
    public function assetTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return AssetType::allOrdered();
    }

    #[Computed]
    public function assetVendors(): \Illuminate\Database\Eloquent\Collection
    {
        return AssetVendor::allOrdered();
    }

    #[Computed]
    public function users(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\User::orderBy('nachname')->orderBy('vorname')->get();
    }

    protected function rules(): array
    {
        $rules = [
            'model' => 'required|string|max:255',
            'asset_type_id' => 'required|exists:intranet_app_assets_asset_types,id',
            'asset_vendor_id' => 'required|exists:intranet_app_assets_asset_vendors,id',
            'units' => 'required|array|min:1',
            'units.*.serial_number' => 'required|string|max:255',
            'units.*.name' => 'nullable|string|max:255',
            'units.*.location' => 'nullable|string|max:255',
            'units.*.user_id' => 'nullable|exists:users,id',
            'units.*.itexia_id' => 'nullable|string|max:255',
            'units.*.order_number' => 'nullable|string|max:255',
            'units.*.invoice_number' => 'nullable|string|max:255',
            'units.*.domain_connection' => 'nullable|string|in:default,schulung',
            'units.*.intune_device_id' => 'nullable|string|max:255',
            'units.*.is_clarification' => 'boolean',
            'units.*.is_missing' => 'boolean',
        ];

        return $rules;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $created = [];
        foreach ($validated['units'] as $unit) {
            $attributes = [
                'model' => $validated['model'],
                'asset_type_id' => $validated['asset_type_id'],
                'asset_vendor_id' => $validated['asset_vendor_id'],
                'serial_number' => $unit['serial_number'],
                'name' => $unit['name'] ?: null,
                'location' => $unit['location'] ?: null,
                'user_id' => $unit['user_id'] ?: null,
                'itexia_id' => $unit['itexia_id'] ?: null,
                'order_number' => $unit['order_number'] ?: null,
                'invoice_number' => $unit['invoice_number'] ?: null,
                'domain_connection' => $unit['domain_connection'] ?: null,
                'intune_device_id' => $unit['intune_device_id'] ?: null,
                'is_clarification' => $unit['is_clarification'] ?? false,
                'is_missing' => $unit['is_missing'] ?? false,
            ];

            $asset = Asset::create($attributes);
            $asset->ensureHandoverForOwner();
            $asset->notes()->create([
                'note' => 'Asset erstellt von ' . auth()->user()->name . '.',
                'user_id' => auth()->id(),
            ]);
            $created[] = $asset;
        }

        $count = count($created);
        session()->flash('success', $count === 1
            ? 'Asset wurde erfolgreich erstellt.'
            : "{$count} Assets wurden erfolgreich erstellt.");

        $this->redirect(
            $count === 1 ? route('apps.assets.show', $created[0]) : route('apps.assets.liste'),
            navigate: true
        );
    }
}; ?>
<div>
<x-intranet-app-assets::assets-layout heading="Neues Asset" subheading="Asset anlegen">
    <form wire:submit="save" class="space-y-8">
        <flux:fieldset legend="Allgemeine Angaben" description="Modell, Typ und Hersteller gelten für alle angelegten Assets.">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <flux:field>
                    <flux:label>Modell <flux:badge size="sm" color="red">Pflicht</flux:badge></flux:label>
                    <flux:input wire:model="model" placeholder="z.B. ThinkPad X1 Carbon" />
                    <flux:error name="model" />
                </flux:field>

                <flux:field>
                    <flux:label>Typ <flux:badge size="sm" color="red">Pflicht</flux:badge></flux:label>
                    <flux:select variant="listbox" searchable wire:model="asset_type_id" placeholder="Typ auswählen…">
                        @foreach($this->assetTypes as $type)
                            <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="asset_type_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Hersteller <flux:badge size="sm" color="red">Pflicht</flux:badge></flux:label>
                    <flux:select variant="listbox" searchable wire:model="asset_vendor_id" placeholder="Hersteller auswählen…">
                        @foreach($this->assetVendors as $vendor)
                            <flux:select.option value="{{ $vendor->id }}">{{ $vendor->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="asset_vendor_id" />
                </flux:field>
            </div>
        </flux:fieldset>

        <flux:separator />

        <div class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="lg">Einzelne Assets</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Pro Einheit wird ein Asset mit den allgemeinen Daten angelegt.</flux:text>
                </div>
                <flux:button type="button" wire:click="addUnit" variant="outline" icon="plus">
                    Weiteres Asset
                </flux:button>
            </div>

            @foreach($this->units as $index => $unit)
                <flux:card class="space-y-6">
                    <div class="flex items-center justify-between gap-4">
                        <flux:heading size="md">Asset {{ $index + 1 }}</flux:heading>
                        @if(count($this->units) > 1)
                            <flux:button type="button" wire:click="removeUnit({{ $index }})" variant="ghost" icon="x-mark" size="sm" class="text-zinc-500">
                                Einheit entfernen
                            </flux:button>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Seriennummer <flux:badge size="sm" color="red">Pflicht</flux:badge></flux:label>
                            <flux:input wire:model="units.{{ $index }}.serial_number" placeholder="z.B. SN-12345" />
                            <flux:error name="units.{{ $index }}.serial_number" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Name / Bezeichnung</flux:label>
                            <flux:input wire:model="units.{{ $index }}.name" placeholder="Optional" />
                            <flux:error name="units.{{ $index }}.name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Standort</flux:label>
                            <flux:input wire:model="units.{{ $index }}.location" placeholder="z.B. Büro 2.13" />
                            <flux:error name="units.{{ $index }}.location" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Besitzer</flux:label>
                            <flux:select variant="listbox" searchable clearable wire:model="units.{{ $index }}.user_id" placeholder="Kein Besitzer">
                                <flux:select.option value="">Kein Besitzer</flux:select.option>
                                @foreach($this->users as $user)
                                    <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="units.{{ $index }}.user_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Itexia-ID</flux:label>
                            <flux:input wire:model="units.{{ $index }}.itexia_id" placeholder="Optional" />
                            <flux:error name="units.{{ $index }}.itexia_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Bestellnummer</flux:label>
                            <flux:input wire:model="units.{{ $index }}.order_number" placeholder="Optional" />
                            <flux:error name="units.{{ $index }}.order_number" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Rechnungsnummer</flux:label>
                            <flux:input wire:model="units.{{ $index }}.invoice_number" placeholder="Optional" />
                            <flux:error name="units.{{ $index }}.invoice_number" />
                        </flux:field>

                        @if($this->showDomainConnectionField)
                            <flux:field>
                                <flux:label>Domain Connection</flux:label>
                                <flux:select variant="listbox" wire:model="units.{{ $index }}.domain_connection" placeholder="Verbindung wählen…" clearable>
                                    <flux:select.option value="">Keine</flux:select.option>
                                    <flux:select.option value="default">Verwaltung</flux:select.option>
                                    <flux:select.option value="schulung">Schulung</flux:select.option>
                                </flux:select>
                                <flux:error name="units.{{ $index }}.domain_connection" />
                            </flux:field>
                        @endif

                        @if($this->showIntuneObjectField)
                            <flux:field>
                                <flux:label>Intune-Geräte-ID</flux:label>
                                <flux:input wire:model="units.{{ $index }}.intune_device_id" placeholder="Optional, z.B. Azure Device ID" />
                                <flux:error name="units.{{ $index }}.intune_device_id" />
                            </flux:field>
                        @endif
                    </div>

                    <div class="flex gap-4 pt-2">
                        <flux:checkbox wire:model="units.{{ $index }}.is_clarification" label="In Klärung" />
                        <flux:checkbox wire:model="units.{{ $index }}.is_missing" label="Vermisst" />
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary">
                @if(count($this->units) === 1)
                    Asset erstellen
                @else
                    {{ count($this->units) }} Assets erstellen
                @endif
            </flux:button>
            <flux:button href="{{ route('apps.assets.liste') }}" variant="ghost">Abbrechen</flux:button>
        </div>
    </form>
</x-intranet-app-assets::assets-layout>
</div>
