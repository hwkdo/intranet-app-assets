<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Contracts\OrderNumberValidationServiceInterface;
use Hwkdo\IntranetAppAssets\Rules\ValidD3InvoiceNumber;
use Hwkdo\IntranetAppAssets\Rules\ValidOrderNumber;
use Hwkdo\IntranetAppAssets\Services\AssetLocationDisplayResolver;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceValidationService;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Hwkdo\IntranetAppAssets\Support\AssetShowBackOrigin;
use Hwkdo\IntranetAppAssets\Support\OwnerChangeActionResolver;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Validator;

new #[Layout('components.layouts.app')] #[Title('Asset bearbeiten')] class extends Component {
    use WithFileUploads;

    public Asset $asset;

    #[Url(except: null)]
    public ?string $from = null;

    #[Url(as: 'sq', except: null)]
    public ?string $searchReturnQuery = null;

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
    public bool $is_in_stock = false;

    #[Validate('nullable|string|in:default,schulung')]
    public ?string $domain_connection = null;

    #[Validate('nullable|string|max:255')]
    public ?string $intune_device_id = null;

    #[Validate('nullable|image|max:10240')]
    public $image = null;

    public function mount(Asset $asset): void
    {
        $this->asset = $asset->load(['owner.standort']);
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
        $this->is_in_stock = $asset->is_in_stock;
        $this->domain_connection = $asset->domain_connection;
        $this->intune_device_id = $asset->intune_device_id;
    }

    /**
     * @return array{value: ?string, label: string, hint: ?string, source: string}
     */
    #[Computed]
    public function locationDisplay(): array
    {
        return AssetLocationDisplayResolver::resolve($this->asset);
    }

    #[Computed]
    public function showBackKey(): string
    {
        return AssetShowBackOrigin::resolve($this->from, auth()->user(), $this->searchReturnQuery)['key'];
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

    #[Computed]
    public function ownerChangeAction(): ?array
    {
        if ($this->asset->user_id === null) {
            return null;
        }

        $pendingReturn = AssetReturn::query()
            ->whereNull('completed_at')
            ->whereHas('handover', fn ($query) => $query->where('asset_id', $this->asset->id))
            ->latest('id')
            ->first();

        $openHandover = Handover::query()
            ->where('asset_id', $this->asset->id)
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->latest('id')
            ->first();

        $rejectedHandover = Handover::query()
            ->where('asset_id', $this->asset->id)
            ->whereNotNull('rejected_at')
            ->latest('rejected_at')
            ->latest('id')
            ->first();

        return OwnerChangeActionResolver::resolve([
            'has_pending_return' => $pendingReturn !== null,
            'has_open_handover' => $openHandover !== null,
            'has_rejected_handover' => $rejectedHandover !== null,
            'is_clarification' => (bool) $this->asset->is_clarification,
            'is_missing' => (bool) $this->asset->is_missing,
            'pending_return_href' => $pendingReturn !== null ? route('apps.assets.admin.return.complete', $pendingReturn) : null,
            'open_handover_href' => $openHandover !== null ? route('apps.assets.admin.open-handover.resolve', $openHandover) : null,
            'rejected_handover_href' => $rejectedHandover !== null ? route('apps.assets.admin.rejected-handover.resolve', $rejectedHandover) : null,
            'clarification_href' => route('apps.assets.admin.clarification.resolve', $this->asset),
            'missing_href' => route('apps.assets.admin.missing.resolve', $this->asset),
        ]);
    }

    public function updatedInvoiceNumber(?string $value): void
    {
        $service = app(D3InvoiceValidationService::class);
        $error = $service->getValidationError($value ?? '');
        if ($error !== null) {
            $this->addError('invoice_number', $error);
        } else {
            $this->clearValidation('invoice_number');
        }
    }

    public function updatedOrderNumber(?string $value): void
    {
        $service = app(OrderNumberValidationServiceInterface::class);
        $error = $service->getValidationError($value ?? '');
        if ($error !== null) {
            $this->addError('order_number', $error);
        } else {
            $this->clearValidation('order_number');
        }
    }

    public function save(): void
    {
        $baseRules = [
            'serial_number' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'asset_type_id' => 'required|exists:intranet_app_assets_asset_types,id',
            'asset_vendor_id' => 'required|exists:intranet_app_assets_asset_vendors,id',
            'name' => 'nullable|string|max:255',
            'itexia_id' => 'nullable|string|max:255',
            'order_number' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'domain_connection' => 'nullable|string|in:default,schulung',
            'intune_device_id' => 'nullable|string|max:255',
        ];

        if ($this->asset->user_id === null) {
            $baseRules['user_id'] = 'nullable|exists:users,id';
            $baseRules['location'] = 'nullable|string|max:255';
            $baseRules['is_in_stock'] = 'boolean';
        }

        $data = [
            'serial_number' => $this->serial_number,
            'model' => $this->model,
            'asset_type_id' => $this->asset_type_id,
            'asset_vendor_id' => $this->asset_vendor_id,
            'name' => $this->name,
            'itexia_id' => $this->itexia_id,
            'order_number' => $this->order_number,
            'invoice_number' => $this->invoice_number,
            'domain_connection' => $this->domain_connection,
            'intune_device_id' => $this->intune_device_id,
        ];
        if ($this->asset->user_id === null) {
            $data['user_id'] = $this->user_id;
            $data['location'] = $this->location;
            $data['is_in_stock'] = $this->is_in_stock;
        }

        $validated = Validator::make($data, $baseRules)->validate();

        $invoiceValidator = Validator::make(
            ['invoice_number' => $validated['invoice_number'] ?? null],
            ['invoice_number' => ['nullable', 'string', 'max:255', new ValidD3InvoiceNumber]]
        );
        if ($invoiceValidator->fails()) {
            $this->addError('invoice_number', $invoiceValidator->errors()->first('invoice_number'));

            return;
        }

        $orderNumberValidator = Validator::make(
            ['order_number' => $validated['order_number'] ?? null],
            ['order_number' => ['nullable', 'string', 'max:255', new ValidOrderNumber]]
        );
        if ($orderNumberValidator->fails()) {
            $this->addError('order_number', $orderNumberValidator->errors()->first('order_number'));

            return;
        }

        $invoiceFilled = isset($validated['invoice_number']) && trim((string) $validated['invoice_number']) !== '';
        $extra = [];
        if ($invoiceFilled && $this->asset->invoice_number_pending) {
            $extra['invoice_number_pending'] = false;
        }

        if ($this->asset->user_id !== null) {
            $validated['user_id'] = $this->asset->user_id;
            $validated['location'] = $this->asset->location;
            $validated['is_missing'] = $this->asset->is_missing;
            $validated['is_clarification'] = $this->asset->is_clarification;
        } else {
            $validated['user_id'] = isset($validated['user_id']) && $validated['user_id'] !== '' && $validated['user_id'] !== null
                ? (int) $validated['user_id']
                : null;
            $validated['is_missing'] = $this->asset->is_missing;
            $validated['is_clarification'] = $this->asset->is_clarification;
        }

        AssetAuditContext::runWith('assets.edit.save', function () use ($validated, $extra): void {
            $this->asset->update(array_merge($validated, $extra));
        });

        if ($this->image !== null) {
            $this->asset->addMedia($this->image->getRealPath())
                ->usingFileName($this->image->getClientOriginalName())
                ->toMediaCollection('image');
        }

        session()->flash('success', 'Asset wurde erfolgreich gespeichert.');
        $this->redirect(route('apps.assets.show', array_filter([
            'asset' => $this->asset,
            'from' => $this->showBackKey,
            'sq' => $this->searchReturnQuery,
        ], fn ($v) => $v !== null && $v !== '')), navigate: true);
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

            @if($asset->user_id === null)
                <flux:field>
                    <flux:label>Standort</flux:label>
                    <flux:input wire:model="location" placeholder="z.B. Büro 2.13 oder Lager" />
                    <flux:error name="location" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="is_in_stock" label="Auf Lager" />
                    <flux:text class="text-xs text-zinc-500">Nur für Assets ohne persönlichen Besitzer (z. B. IT-Lager). Gemeinschaftsgeräte bleiben ohne Häkchen.</flux:text>
                    <flux:error name="is_in_stock" />
                </flux:field>

                <flux:field>
                    <flux:label>Besitzer (Erstzuweisung)</flux:label>
                    <flux:select variant="listbox" searchable clearable wire:model="user_id" placeholder="Kein Besitzer">
                        <flux:select.option value="">Kein Besitzer</flux:select.option>
                        @foreach($this->users as $user)
                            <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="user_id" />
                    <flux:text class="text-xs text-zinc-500">Ohne Besitzer bleibt das Asset im Pool; mit Auswahl wird eine Übergabe erzeugt.</flux:text>
                </flux:field>
            @else
                <flux:field class="sm:col-span-2">
                    <flux:label>Zuordnung (nur über Klärung / Übergaben änderbar)</flux:label>
                    <flux:callout variant="subtle" icon="information-circle" class="mt-1">
                        <flux:callout.text>
                            <strong>Besitzer:</strong> {{ $asset->owner?->name ?? '—' }}
                            · <strong>{{ $this->locationDisplay['label'] }}:</strong>
                            @if(filled($this->locationDisplay['value']))
                                {{ $this->locationDisplay['value'] }}
                                <span class="text-zinc-500">({{ $this->locationDisplay['hint'] }})</span>
                            @else
                                —
                                @if(filled($this->locationDisplay['hint']))
                                    <span class="text-zinc-500">({{ $this->locationDisplay['hint'] }})</span>
                                @endif
                            @endif
                            · <strong>Vermisst:</strong> {{ $asset->is_missing ? 'Ja' : 'Nein' }}
                            · <strong>In Klärung:</strong> {{ $asset->is_clarification ? 'Ja' : 'Nein' }}
                            · <strong>Auf Lager:</strong> {{ $asset->is_in_stock ? 'Ja' : 'Nein' }}
                            @if($this->ownerChangeAction)
                                <br>
                                <a href="{{ $this->ownerChangeAction['href'] }}" wire:navigate class="text-primary-600 underline hover:no-underline dark:text-primary-400">
                                    {{ $this->ownerChangeAction['label'] }}
                                </a>
                                <span class="text-zinc-500">- {{ $this->ownerChangeAction['hint'] }}</span>
                            @else
                                <br>
                                <span class="text-zinc-500">Kein direkter Spezialfall gefunden. Bei Bedarf bitte über Rückgabeprozess neu zuordnen.</span>
                            @endif
                        </flux:callout.text>
                    </flux:callout>
                </flux:field>
            @endif

            <flux:field>
                <flux:label>Itexia-ID</flux:label>
                <flux:input wire:model="itexia_id" placeholder="Optional" />
                <flux:error name="itexia_id" />
            </flux:field>

            <x-intranet-app-assets::order-number-input name="order_number" wire:model.live.debounce.800ms="order_number" placeholder="Optional" />

            @if($asset->invoice_number_pending)
            <flux:callout variant="warning" icon="exclamation-triangle" class="col-span-full">
                Rechnungsnummer noch offen – bitte nachtragen.
            </flux:callout>
            @endif
            <x-intranet-app-assets::invoice-number-input name="invoice_number" wire:model.live.debounce.800ms="invoice_number" placeholder="Optional" />

            <flux:field class="col-span-full">
                <flux:label>Bild</flux:label>
                @if($asset->getFirstMedia('image'))
                    <a href="{{ $asset->getFirstMedia('image')->getFullUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-block mb-2">
                        <img src="{{ $asset->getFirstMedia('thumbnail')?->getFullUrl() ?? $asset->getFirstMedia('image')->getFullUrl() }}" alt="Aktuelles Asset-Bild" class="h-28 w-28 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700" />
                    </a>
                @endif
                <input type="file" wire:model="image" accept="image/*" class="block w-full text-sm text-zinc-600 dark:text-zinc-300" />
                <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Optional: Neues Bild hochladen, um das aktuelle zu ersetzen (max. 10 MB).</flux:text>
                <flux:error name="image" />
            </flux:field>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary">Speichern</flux:button>
            <flux:button href="{{ route('apps.assets.show', array_filter(['asset' => $asset, 'from' => $this->showBackKey, 'sq' => $this->searchReturnQuery], fn ($v) => $v !== null && $v !== '')) }}" variant="ghost">Abbrechen</flux:button>
        </div>
    </form>
</x-intranet-app-assets::assets-layout>
</div>