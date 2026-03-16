<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Asset bearbeiten')] class extends Component {
    public Asset $asset;

    #[Validate('required|string|max:255')]
    public string $serial_number = '';

    #[Validate('required|string|max:255')]
    public string $model = '';

    #[Validate('required|exists:intranet_app_assets_asset_types,id')]
    public string $asset_type_id = '';

    #[Validate('required|exists:intranet_app_assets_asset_vendors,id')]
    public string $asset_vendor_id = '';

    #[Validate('nullable|exists:users,id')]
    public ?string $user_id = null;

    #[Validate('nullable|string|max:255')]
    public ?string $name = null;

    #[Validate('nullable|string|max:255')]
    public ?string $location = null;

    #[Validate('nullable|string|max:255')]
    public ?string $itexia_id = null;

    #[Validate('nullable|string|max:255')]
    public ?string $order_number = null;

    #[Validate('nullable|string|max:255')]
    public ?string $invoice_number = null;

    public bool $is_clarification = false;
    public bool $is_missing = false;

    #[Validate('nullable|string|in:default,schulung')]
    public ?string $domain_connection = null;

    #[Validate('nullable|string|max:255')]
    public ?string $intune_device_id = null;

    public function mount(Asset $asset): void
    {
        $this->asset = $asset;
        $this->serial_number = $asset->serial_number;
        $this->model = $asset->model;
        $this->asset_type_id = (string) $asset->asset_type_id;
        $this->asset_vendor_id = (string) $asset->asset_vendor_id;
        $this->user_id = $asset->user_id ? (string) $asset->user_id : null;
        $this->name = $asset->name;
        $this->location = $asset->location;
        $this->itexia_id = $asset->itexia_id;
        $this->order_number = $asset->order_number;
        $this->invoice_number = $asset->invoice_number;
        $this->is_clarification = $asset->is_clarification;
        $this->is_missing = $asset->is_missing;
        $this->domain_connection = $asset->domain_connection;
        $this->intune_device_id = $asset->intune_device_id;
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

    public function save(): void
    {
        $validated = $this->validate();

        $this->asset->update($validated);
        $this->asset->ensureHandoverForOwner();

        session()->flash('success', 'Asset wurde erfolgreich gespeichert.');
        $this->redirect(route('apps.assets.show', $this->asset), navigate: true);
    }
}; ?>
<div>
<x-intranet-app-assets::assets-layout
    heading="Asset bearbeiten"
    subheading="{{ $asset->display_name }}"
>
    <form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <flux:field>
                <flux:label>Seriennummer <flux:badge size="sm" color="red">Pflicht</flux:badge></flux:label>
                <flux:input wire:model="serial_number" placeholder="z.B. SN-12345" />
                <flux:error name="serial_number" />
            </flux:field>

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

            @if($this->showDomainConnectionField)
            <flux:field>
                <flux:label>Domain Connection</flux:label>
                <flux:select variant="listbox" wire:model="domain_connection" placeholder="Verbindung wählen…" clearable>
                    <flux:select.option value="">Keine</flux:select.option>
                    <flux:select.option value="default">Verwaltung</flux:select.option>
                    <flux:select.option value="schulung">Schulung</flux:select.option>
                </flux:select>
                <flux:error name="domain_connection" />
            </flux:field>
            @endif

            @if($this->showIntuneObjectField)
            <flux:field>
                <flux:label>Intune-Geräte-ID</flux:label>
                <flux:input wire:model="intune_device_id" placeholder="Optional, z.B. Azure Device ID" />
                <flux:error name="intune_device_id" />
            </flux:field>
            @endif

            <flux:field>
                <flux:label>Hersteller <flux:badge size="sm" color="red">Pflicht</flux:badge></flux:label>
                <flux:select variant="listbox" searchable wire:model="asset_vendor_id" placeholder="Hersteller auswählen…">
                    @foreach($this->assetVendors as $vendor)
                        <flux:select.option value="{{ $vendor->id }}">{{ $vendor->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="asset_vendor_id" />
            </flux:field>

            <flux:field>
                <flux:label>Name / Bezeichnung</flux:label>
                <flux:input wire:model="name" placeholder="Optional" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Standort</flux:label>
                <flux:input wire:model="location" placeholder="z.B. Büro 2.13" />
                <flux:error name="location" />
            </flux:field>

            <flux:field>
                <flux:label>Besitzer</flux:label>
                <flux:select variant="listbox" searchable clearable wire:model="user_id" placeholder="Kein Besitzer">
                    <flux:select.option value="">Kein Besitzer</flux:select.option>
                    @foreach($this->users as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="user_id" />
            </flux:field>

            <flux:field>
                <flux:label>Itexia-ID</flux:label>
                <flux:input wire:model="itexia_id" placeholder="Optional" />
                <flux:error name="itexia_id" />
            </flux:field>

            <flux:field>
                <flux:label>Bestellnummer</flux:label>
                <flux:input wire:model="order_number" placeholder="Optional" />
                <flux:error name="order_number" />
            </flux:field>

            <flux:field>
                <flux:label>Rechnungsnummer</flux:label>
                <flux:input wire:model="invoice_number" placeholder="Optional" />
                <flux:error name="invoice_number" />
            </flux:field>
        </div>

        <div class="flex gap-4">
            <flux:checkbox wire:model="is_clarification" label="In Klärung" />
            <flux:checkbox wire:model="is_missing" label="Vermisst" />
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary">Speichern</flux:button>
            <flux:button href="{{ route('apps.assets.show', $asset) }}" variant="ghost">Abbrechen</flux:button>
        </div>
    </form>
</x-intranet-app-assets::assets-layout>
</div>