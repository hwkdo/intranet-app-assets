<?php

use App\Models\User;
use Flux\Flux;
use Hwkdo\IntranetAppAssets\ItexiaCreation;
use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Contracts\OrderNumberValidationServiceInterface;
use Hwkdo\IntranetAppAssets\Rules\ValidD3InvoiceNumber;
use Hwkdo\IntranetAppAssets\Rules\ValidOrderNumber;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceValidationService;
use Hwkdo\IntranetAppAssets\SeventhingsMappingConfig;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] #[Title('Neues Asset – Assistent')] class extends Component
{
    use WithFileUploads;

    public int $step = 1;

    /** bestellung | beschaffung | mobilfunkvertrag */
    public string $variant = '';

    /** Wird in Schritt 2 gesetzt: true = Wert >= Wertgrenze, false = Wert < Wertgrenze */
    public ?bool $valueOver250 = null;

    public string $model = '';

    public string $asset_type_id = '';

    public string $asset_vendor_id = '';

    /** Sucheingaben für Combobox (Typ / Hersteller), :filter="false" + serverseitige Liste */
    public string $asset_type_search = '';

    public string $asset_vendor_search = '';

    /** Einheiten: pro Eintrag ein Asset mit spezifischen Feldern (wie Direkteingabe) */
    public array $units = [];

    /** Optionale Bild-Uploads je Einheit (indexbasiert). */
    public array $unit_images = [];

    public function mount(): void
    {
        $this->units = [$this->defaultUnit()];
        $this->unit_images = [null];
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
            'invoice_number_unknown' => false,
            'is_clarification' => false,
            'is_missing' => false,
        ];
    }

    public function addUnit(): void
    {
        $newUnit = $this->defaultUnit();
        $lastName = $this->getLastUnitName();
        $suggestedName = $this->incrementNameSuffix($lastName);
        if ($suggestedName !== null) {
            $newUnit['name'] = $suggestedName;
        }
        if (count($this->units) > 0) {
            $first = $this->units[0];
            if (filled($first['order_number'] ?? null)) {
                $newUnit['order_number'] = trim((string) $first['order_number']);
            }
            if (filled($first['invoice_number'] ?? null)) {
                $newUnit['invoice_number'] = trim((string) $first['invoice_number']);
            }
            $newUnit['invoice_number_unknown'] = (bool) ($first['invoice_number_unknown'] ?? false);
        }
        $this->units[] = $newUnit;
        $this->unit_images[] = null;
    }

    protected function getLastUnitName(): ?string
    {
        $last = end($this->units);

        return isset($last['name']) && (string) $last['name'] !== '' ? (string) $last['name'] : null;
    }

    /**
     * Erhöht eine am Ende stehende Zahl im Namen (z. B. Device01 -> Device02).
     */
    protected function incrementNameSuffix(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        if (preg_match('/^(.*?)(\d+)$/', $name, $m)) {
            $prefix = $m[1];
            $numStr = $m[2];
            $nextNum = (int) $numStr + 1;
            $length = strlen($numStr);

            return $prefix.str_pad((string) $nextNum, $length, '0', STR_PAD_LEFT);
        }

        return null;
    }

    public function removeUnit(int $index): void
    {
        if (count($this->units) <= 1) {
            return;
        }
        array_splice($this->units, $index, 1);
        array_splice($this->unit_images, $index, 1);
    }

    public function selectVariant(string $variant): void
    {
        $this->variant = $variant;
        $this->valueOver250 = null;
        $this->step = 2;
    }

    public function selectValue(bool $over250): void
    {
        $this->valueOver250 = $over250;
        $this->step = 3;
    }

    public function back(): void
    {
        if ($this->step === 3) {
            $this->step = 2;
        } elseif ($this->step === 2) {
            $this->step = 1;
            $this->variant = '';
            $this->valueOver250 = null;
        }
    }

    #[Computed]
    public function appSettings(): AppSettings
    {
        $settings = IntranetAppAssetsSettings::current()?->settings;

        return $settings ?? new AppSettings;
    }

    #[Computed]
    public function wertgrenzeItexia(): int
    {
        return $this->appSettings->wertgrenzeItexia;
    }

    #[Computed]
    public function showValueStep(): bool
    {
        return $this->variant !== '';
    }

    #[Computed]
    public function showOrderNumber(): bool
    {
        return $this->variant === 'bestellung';
    }

    #[Computed]
    public function showInvoiceNumber(): bool
    {
        return in_array($this->variant, ['bestellung', 'beschaffung', 'mobilfunkvertrag'], true);
    }

    #[Computed]
    public function showItexiaId(): bool
    {
        return in_array($this->variant, ['bestellung', 'beschaffung', 'mobilfunkvertrag'], true);
    }

    #[Computed]
    public function orderNumberRequired(): bool
    {
        if ($this->variant !== 'bestellung') {
            return false;
        }

        return $this->valueOver250 === true || $this->appSettings->benBenoetigtWennWertKleinerGrenze;
    }

    #[Computed]
    public function invoiceNumberRequired(): bool
    {
        return in_array($this->variant, ['beschaffung', 'mobilfunkvertrag'], true) && $this->valueOver250 === true;
    }

    #[Computed]
    public function itexiaIdRequired(): bool
    {
        if ($this->variant === 'bestellung' || $this->variant === 'beschaffung') {
            return $this->valueOver250 === true;
        }
        if ($this->variant === 'mobilfunkvertrag') {
            return true; // immer Pflicht (bei >250 und <250)
        }

        return false;
    }

    /** Ob nach Speichern für Assets mit Itexia-ID automatisch in Itexia angelegt werden soll (Mobilfunkvertrag, Wert < 250). */
    #[Computed]
    public function shouldCreateInItexiaAfterSave(): bool
    {
        return $this->variant === 'mobilfunkvertrag' && $this->valueOver250 === false;
    }

    #[Computed]
    public function filteredAssetTypes(): Collection
    {
        $term = trim($this->asset_type_search);
        $q = AssetType::query()->orderBy('name');

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $q->where('name', 'like', $like);
        }

        $results = $q->limit(75)->get();

        if (filled($this->asset_type_id)) {
            $selected = AssetType::query()->find((int) $this->asset_type_id);
            if ($selected !== null && ! $results->contains(fn (AssetType $t): bool => (int) $t->id === (int) $selected->id)) {
                $results = $results->prepend($selected)->unique('id')->values();
            }
        }

        return $results;
    }

    #[Computed]
    public function filteredAssetVendors(): Collection
    {
        $term = trim($this->asset_vendor_search);
        $q = AssetVendor::query()->orderBy('name');

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $q->where('name', 'like', $like);
        }

        $results = $q->limit(75)->get();

        if (filled($this->asset_vendor_id)) {
            $selected = AssetVendor::query()->find((int) $this->asset_vendor_id);
            if ($selected !== null && ! $results->contains(fn (AssetVendor $v): bool => (int) $v->id === (int) $selected->id)) {
                $results = $results->prepend($selected)->unique('id')->values();
            }
        }

        return $results;
    }

    public function updatedAssetTypeSearch(): void
    {
        $this->resetErrorBag('asset_type_search');
    }

    public function updatedAssetVendorSearch(): void
    {
        $this->resetErrorBag('asset_vendor_search');
    }

    public function createAssetTypeFromWizard(): void
    {
        $validator = Validator::make(
            ['asset_type_search' => trim($this->asset_type_search)],
            [
                'asset_type_search' => [
                    'required',
                    'string',
                    'min:2',
                    'max:255',
                    'unique:intranet_app_assets_asset_types,name',
                ],
            ],
            [
                'asset_type_search.required' => 'Bitte einen Namen für den neuen Typ eingeben.',
                'asset_type_search.min' => 'Der Name muss mindestens 2 Zeichen haben.',
                'asset_type_search.unique' => 'Dieser Typ existiert bereits – bitte in der Liste auswählen.',
            ]
        );

        if ($validator->fails()) {
            $this->addError('asset_type_search', $validator->errors()->first('asset_type_search'));

            return;
        }

        $name = $validator->validated()['asset_type_search'];

        $type = AssetType::query()->create([
            'name' => $name,
            'is_domain_object' => false,
            'is_intune_object' => false,
            'itexia_creation_allowed' => false,
        ]);

        $this->asset_type_id = (string) $type->id;
        $this->asset_type_search = '';
        $this->resetErrorBag('asset_type_search');

        Flux::toast('Typ „'.$type->name.'“ wurde angelegt.', variant: 'success');
    }

    public function createAssetVendorFromWizard(): void
    {
        $validator = Validator::make(
            ['asset_vendor_search' => trim($this->asset_vendor_search)],
            [
                'asset_vendor_search' => [
                    'required',
                    'string',
                    'min:2',
                    'max:255',
                    'unique:intranet_app_assets_asset_vendors,name',
                ],
            ],
            [
                'asset_vendor_search.required' => 'Bitte einen Namen für den neuen Hersteller eingeben.',
                'asset_vendor_search.min' => 'Der Name muss mindestens 2 Zeichen haben.',
                'asset_vendor_search.unique' => 'Dieser Hersteller existiert bereits – bitte in der Liste auswählen.',
            ]
        );

        if ($validator->fails()) {
            $this->addError('asset_vendor_search', $validator->errors()->first('asset_vendor_search'));

            return;
        }

        $name = $validator->validated()['asset_vendor_search'];

        $vendor = AssetVendor::query()->create([
            'name' => $name,
        ]);

        $this->asset_vendor_id = (string) $vendor->id;
        $this->asset_vendor_search = '';
        $this->resetErrorBag('asset_vendor_search');

        Flux::toast('Hersteller „'.$vendor->name.'“ wurde angelegt.', variant: 'success');
    }

    #[Computed]
    public function users(): Collection
    {
        return User::orderBy('nachname')->orderBy('vorname')->get();
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
            'units.*.user_id' => 'nullable|exists:users,id',
            'units.*.order_number' => ['nullable', 'string', 'max:255', new ValidOrderNumber],
            'units.*.invoice_number' => ['nullable', 'string', 'max:255', new ValidD3InvoiceNumber],
            'units.*.invoice_number_unknown' => 'boolean',
            'units.*.itexia_id' => 'nullable|string|max:255',
            'units.*.is_clarification' => 'boolean',
            'units.*.is_missing' => 'boolean',
            'unit_images' => 'array',
            'unit_images.*' => 'nullable|image|max:10240',
        ];

        foreach (array_keys($this->units) as $i) {
            $rules["units.{$i}.location"] = "required_if:units.{$i}.user_id,null|nullable|string|max:255";
        }

        if ($this->orderNumberRequired) {
            $rules['units.*.order_number'] = ['required', 'string', 'max:255', new ValidOrderNumber];
        }
        if ($this->invoiceNumberRequired) {
            foreach (array_keys($this->units) as $i) {
                $rules["units.{$i}.invoice_number"] = ['required_unless:units.'.$i.'.invoice_number_unknown,true', 'nullable', 'string', 'max:255', new ValidD3InvoiceNumber];
            }
        } else {
            $rules['units.*.invoice_number'] = ['nullable', 'string', 'max:255', new ValidD3InvoiceNumber];
        }
        if ($this->itexiaIdRequired) {
            $rules['units.*.itexia_id'] = 'required|string|max:255';
        }

        return $rules;
    }

    public function updatedUnits(): void
    {
        $invoiceService = app(D3InvoiceValidationService::class);
        $orderNumberService = app(OrderNumberValidationServiceInterface::class);
        foreach ($this->units as $i => $unit) {
            $attr = 'units.'.$i.'.invoice_number';
            if (! $this->showInvoiceNumber || ($unit['invoice_number_unknown'] ?? false)) {
                $this->clearValidation($attr);
            } else {
                $value = $unit['invoice_number'] ?? '';
                $error = $invoiceService->getValidationError(is_string($value) ? $value : '');
                if ($error !== null) {
                    $this->addError($attr, $error);
                } else {
                    $this->clearValidation($attr);
                }
            }
            if ($this->showOrderNumber) {
                $orderAttr = 'units.'.$i.'.order_number';
                $orderValue = $unit['order_number'] ?? '';
                $orderError = $orderNumberService->getValidationError(is_string($orderValue) ? $orderValue : '');
                if ($orderError !== null) {
                    $this->addError($orderAttr, $orderError);
                } else {
                    $this->clearValidation($orderAttr);
                }
            }
        }
    }

    public function save(): void
    {
        $validated = $this->validate();

        $created = [];
        $creatorId = auth()->id();
        foreach ($validated['units'] as $index => $unit) {
            $invoiceNumberUnknown = (bool) ($unit['invoice_number_unknown'] ?? false);
            $invoiceNumber = $this->showInvoiceNumber && ! $invoiceNumberUnknown
                ? (isset($unit['invoice_number']) && trim((string) $unit['invoice_number']) !== '' ? trim((string) $unit['invoice_number']) : null)
                : null;

            $attributes = [
                'model' => $validated['model'],
                'asset_type_id' => $validated['asset_type_id'],
                'asset_vendor_id' => $validated['asset_vendor_id'],
                'serial_number' => $unit['serial_number'],
                'name' => $unit['name'] ?: null,
                'location' => $unit['location'] ?: null,
                'user_id' => $unit['user_id'] ?: null,
                'order_number' => $this->showOrderNumber ? ($unit['order_number'] ?? null) : null,
                'invoice_number' => $invoiceNumber,
                'invoice_number_pending' => $invoiceNumberUnknown,
                'created_by_user_id' => $creatorId,
                'itexia_id' => $this->showItexiaId ? ($unit['itexia_id'] ?? null) : null,
                'is_clarification' => $unit['is_clarification'] ?? false,
                'is_missing' => $unit['is_missing'] ?? false,
            ];

            $asset = Asset::create($attributes);
            if (isset($this->unit_images[$index]) && $this->unit_images[$index] !== null) {
                $asset->addMedia($this->unit_images[$index]->getRealPath())
                    ->usingFileName($this->unit_images[$index]->getClientOriginalName())
                    ->toMediaCollection('image');
            }
            $asset->notes()->create([
                'note' => 'Asset erstellt von '.auth()->user()->name.' (Assistent).',
                'user_id' => auth()->id(),
            ]);
            $created[] = $asset;

            if ($this->shouldCreateInItexiaAfterSave && filled($asset->itexia_id)) {
                $this->runCreateInItexia($asset);
            }
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

    private function runCreateInItexia(Asset $asset): void
    {
        $asset->loadMissing(['type', 'vendor', 'owner']);
        if (! ItexiaCreation::canCreateInItexia($asset)) {
            return;
        }

        $seventhingsClass = \Hwkdo\SeventhingsLaravel\SeventhingsLaravel::class;
        if (! class_exists($seventhingsClass) || ! app()->bound($seventhingsClass)) {
            Flux::toast('Seventhings ist nicht verfügbar – Asset wurde erstellt, Anlage in Itexia unterblieb.', variant: 'warning');

            return;
        }

        $client = app()->make($seventhingsClass);
        $existing = $client->findAsset(trim((string) $asset->itexia_id));
        if ($existing !== null) {
            $uuid = SeventhingsMappingConfig::getSeventhingsObjectId($existing);
            if ($uuid !== null && $uuid !== '') {
                $asset->update(['itexia_uuid' => (string) $uuid, 'itexia_check_at' => now()]);
                ItexiaCreation::syncLocalImageToItexia($asset, $client, (string) $uuid);
                Flux::toast('Asset erstellt. Existierte bereits in Itexia; Verknüpfung wurde gesetzt.', variant: 'success');
            }

            return;
        }

        try {
            $payload = ItexiaCreation::buildCreatePayload($asset);
            $uuid = $client->createAsset($payload);
            $asset->update([
                'itexia_uuid' => $uuid,
                'itexia_check_at' => now(),
            ]);
            ItexiaCreation::syncLocalImageToItexia($asset, $client, $uuid);
            Flux::toast('Asset wurde erstellt und in Itexia/Seventhings angelegt.', variant: 'success');
        } catch (\Throwable $e) {
            Flux::toast('Asset erstellt. Anlage in Itexia fehlgeschlagen: '.$e->getMessage(), variant: 'danger');
        }
    }
}; ?>
<div>
<x-intranet-app-assets::assets-layout heading="Neues Asset – Assistent" subheading="Schritt für Schritt zum neuen Asset">
    @if($step === 1)
        <div class="space-y-6">
            <flux:heading size="lg" class="dark:text-white">Woher stammt die Hardware?</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-200">Wählen Sie die passende Einstiegsvariante.</flux:text>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:card class="cursor-pointer transition hover:ring-2 hover:ring-primary-500 dark:hover:ring-primary-400" wire:click="selectVariant('bestellung')">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 rounded-lg bg-primary-100 p-3 dark:bg-primary-900/30">
                            <flux:icon.shopping-cart class="size-8 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <flux:heading size="md" class="dark:text-white">Aus Bestellung</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-200">BEN und Itexia-ID bei Wert ab {{ $this->wertgrenzeItexia }} € Pflicht. Rechnungsnr. optional.</flux:text>
                        </div>
                    </div>
                </flux:card>
                <flux:card class="cursor-pointer transition hover:ring-2 hover:ring-primary-500 dark:hover:ring-primary-400" wire:click="selectVariant('beschaffung')">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 rounded-lg bg-primary-100 p-3 dark:bg-primary-900/30">
                            <flux:icon.truck class="size-8 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <flux:heading size="md" class="dark:text-white">Aus Beschaffung</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-200">Rechnungsnr. und Itexia-ID bei Wert ab {{ $this->wertgrenzeItexia }} € Pflicht. Kein BEN.</flux:text>
                        </div>
                    </div>
                </flux:card>
                <flux:card class="cursor-pointer transition hover:ring-2 hover:ring-primary-500 dark:hover:ring-primary-400" wire:click="selectVariant('mobilfunkvertrag')">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 rounded-lg bg-primary-100 p-3 dark:bg-primary-900/30">
                            <flux:icon.device-phone-mobile class="size-8 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <flux:heading size="md" class="dark:text-white">Aus Mobilfunkvertrag</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-200">Rechnungsnr. + Itexia-ID bei &gt;250 €; unter 250 € nur Itexia-ID + Anlage in Itexia.</flux:text>
                        </div>
                    </div>
                </flux:card>
            </div>
        </div>
    @elseif($step === 2)
        <div class="space-y-6">
            <flux:heading size="lg" class="dark:text-white">Wert der Hardware laut Rechnung</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-200">Über oder unter 250 Euro brutto?</flux:text>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:card class="cursor-pointer transition hover:ring-2 hover:ring-primary-500 dark:hover:ring-primary-400" wire:click="selectValue(true)">
                    <div class="flex items-center gap-4">
                        <div class="shrink-0 rounded-lg bg-primary-100 p-3 dark:bg-primary-900/30">
                            <flux:icon.currency-euro class="size-8 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <flux:heading size="md" class="dark:text-white">Über 250 € brutto</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-200">Wert der Hardware laut Rechnung ab {{ $this->wertgrenzeItexia }} Euro brutto</flux:text>
                        </div>
                    </div>
                </flux:card>
                <flux:card class="cursor-pointer transition hover:ring-2 hover:ring-primary-500 dark:hover:ring-primary-400" wire:click="selectValue(false)">
                    <div class="flex items-center gap-4">
                        <div class="shrink-0 rounded-lg bg-primary-100 p-3 dark:bg-primary-900/30">
                            <flux:icon.banknotes class="size-8 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <flux:heading size="md" class="dark:text-white">Unter 250 € brutto</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-200">Wert der Hardware laut Rechnung unter {{ $this->wertgrenzeItexia }} Euro brutto</flux:text>
                        </div>
                    </div>
                </flux:card>
            </div>
            <flux:button type="button" variant="ghost" wire:click="back">Zurück</flux:button>
        </div>
    @else
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
                        <flux:select
                            wire:model="asset_type_id"
                            variant="combobox"
                            :filter="false"
                            placeholder="Typ auswählen…"
                        >
                            <x-slot name="input">
                                <flux:select.input
                                    wire:model.live.debounce.300ms="asset_type_search"
                                    placeholder="Typ suchen oder neu anlegen…"
                                    :invalid="$errors->has('asset_type_search')"
                                />
                            </x-slot>
                            @foreach($this->filteredAssetTypes as $type)
                                <flux:select.option value="{{ $type->id }}" wire:key="wizard-asset-type-{{ $type->id }}">{{ $type->name }}</flux:select.option>
                            @endforeach
                            <flux:select.option.create wire:click="createAssetTypeFromWizard" min-length="2">
                                Typ „<span wire:text="asset_type_search"></span>“ anlegen
                            </flux:select.option.create>
                        </flux:select>
                        <flux:error name="asset_type_id" />
                        <flux:error name="asset_type_search" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Hersteller <flux:badge size="sm" color="red">Pflicht</flux:badge></flux:label>
                        <flux:select
                            wire:model="asset_vendor_id"
                            variant="combobox"
                            :filter="false"
                            placeholder="Hersteller auswählen…"
                        >
                            <x-slot name="input">
                                <flux:select.input
                                    wire:model.live.debounce.300ms="asset_vendor_search"
                                    placeholder="Hersteller suchen oder neu anlegen…"
                                    :invalid="$errors->has('asset_vendor_search')"
                                />
                            </x-slot>
                            @foreach($this->filteredAssetVendors as $vendor)
                                <flux:select.option value="{{ $vendor->id }}" wire:key="wizard-asset-vendor-{{ $vendor->id }}">{{ $vendor->name }}</flux:select.option>
                            @endforeach
                            <flux:select.option.create wire:click="createAssetVendorFromWizard" min-length="2">
                                Hersteller „<span wire:text="asset_vendor_search"></span>“ anlegen
                            </flux:select.option.create>
                        </flux:select>
                        <flux:error name="asset_vendor_id" />
                        <flux:error name="asset_vendor_search" />
                    </flux:field>
                </div>
            </flux:fieldset>

            <flux:separator />

            <div class="space-y-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <flux:heading size="lg">Einzelne Assets</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-200">Pro Einheit wird ein Asset mit den allgemeinen Daten angelegt. Namen mit Zahl am Ende (z. B. Device01) werden beim Hinzufügen hochgezählt.</flux:text>
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
                                <flux:input wire:model="units.{{ $index }}.name" placeholder="z. B. Device01 – wird beim Hinzufügen hochgezählt" />
                                <flux:error name="units.{{ $index }}.name" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Standort <flux:badge size="sm" color="red">Pflicht wenn kein Besitzer</flux:badge></flux:label>
                                <flux:input wire:model="units.{{ $index }}.location" placeholder="z.B. Büro 2.13" />
                                <flux:error name="units.{{ $index }}.location" />
                            </flux:field>
                            <flux:field class="sm:col-span-2">
                                <flux:label>Bild</flux:label>
                                <input type="file" wire:model="unit_images.{{ $index }}" accept="image/*" class="block w-full text-sm text-zinc-600 dark:text-zinc-300" />
                                <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Optionales Bild für dieses Asset (max. 10 MB).</flux:text>
                                <flux:error name="unit_images.{{ $index }}" />
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
                            @if($this->showOrderNumber)
                                <x-intranet-app-assets::order-number-input
                                    name="units.{{ $index }}.order_number"
                                    wire:model.live.debounce.800ms="units.{{ $index }}.order_number"
                                    :placeholder="$this->orderNumberRequired ? 'Pflicht bei Wert ab ' . $this->wertgrenzeItexia . ' €' : 'Optional'"
                                    :required="$this->orderNumberRequired"
                                />
                            @endif
                            @if($this->showInvoiceNumber)
                                <flux:field>
                                    @if($this->invoiceNumberRequired)
                                        <flux:checkbox wire:model.live="units.{{ $index }}.invoice_number_unknown" label="Rechnungsnr. noch nicht bekannt" class="mb-2" />
                                    @endif
                                    @if(!($unit['invoice_number_unknown'] ?? false))
                                        <x-intranet-app-assets::invoice-number-input
                                            name="units.{{ $index }}.invoice_number"
                                            wire:model.live.debounce.800ms="units.{{ $index }}.invoice_number"
                                            :placeholder="$this->invoiceNumberRequired ? 'Pflicht' : 'Optional'"
                                            :required="$this->invoiceNumberRequired"
                                        />
                                    @endif
                                </flux:field>
                            @endif
                            @if($this->showItexiaId)
                                <flux:field>
                                    <flux:label>Itexia-ID @if($this->itexiaIdRequired)<flux:badge size="sm" color="red">Pflicht</flux:badge>@endif</flux:label>
                                    <flux:input wire:model="units.{{ $index }}.itexia_id" placeholder="{{ $this->itexiaIdRequired ? 'Pflicht' : 'Optional' }}" />
                                    @if($variant === 'mobilfunkvertrag' && !$valueOver250)
                                        <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-200">Unter {{ $this->wertgrenzeItexia }} €: Itexia-ID Pflicht, Anlage in Itexia erfolgt automatisch nach Speichern.</flux:text>
                                    @endif
                                    <flux:error name="units.{{ $index }}.itexia_id" />
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
                <flux:button type="button" variant="ghost" wire:click="back">Zurück</flux:button>
                <flux:button href="{{ route('apps.assets.liste') }}" variant="ghost">Abbrechen</flux:button>
            </div>
        </form>
    @endif
</x-intranet-app-assets::assets-layout>
</div>
