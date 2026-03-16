<?php

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\SeventhingsMapping;
use Hwkdo\IntranetAppAssets\SeventhingsMappingConfig;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /** Itexia-ID (Barcode) des Assets – wenn leer, wird nur Hinweis angezeigt. */
    public ?string $itexiaId = null;

    /** Asset-ID für Vergleich und Übernahme (lokale Werte, Seventhings-Update). */
    public ?int $assetId = null;

    /** @var array<string, mixed>|null */
    public ?array $itexiaData = null;

    /** @var array<string, mixed> Für Mapping/Vergleich (itexia_attribute => value). */
    public array $itexiaArrayForMapping = [];

    /** Seventhings-Objekt-UUID für PATCH-Update. */
    public ?string $itexiaObjectId = null;

    public ?string $itexiaError = null;

    /** true = Daten geladen, false = Fehler, null = noch nicht geladen */
    public ?bool $loaded = null;

    public bool $showSeventhingsSearchModal = false;

    /** @var array<int, array{label: string, local_attribute: string, itexia_attribute: string, localValue: mixed, itexiaValue: mixed, match: bool}> */
    public array $compareData = [];

    public function updatedItexiaId(): void
    {
        $this->loaded = null;
        $this->itexiaError = null;
        $this->itexiaData = null;
        $this->itexiaArrayForMapping = [];
        $this->itexiaObjectId = null;
        $this->compareData = [];
        if (trim((string) $this->itexiaId) !== '') {
            $this->loadItexiaData();
        }
    }

    #[Computed]
    public function isItexiaNotFoundError(): bool
    {
        if ($this->itexiaError === null || $this->itexiaError === '') {
            return false;
        }

        return str_starts_with($this->itexiaError, 'Kein Itexia-Asset mit Barcode');
    }

    public function openSeventhingsSearchModal(): void
    {
        $this->showSeventhingsSearchModal = true;
    }

    #[On('itexia-id-set')]
    public function closeSeventhingsSearchModal(): void
    {
        $this->showSeventhingsSearchModal = false;
    }

    /**
     * Alle Itexia-Felder in Anzeige-Reihenfolge; Felder mit Mapping haben zusätzlich compareRow und compareIndex.
     *
     * @return array<int, array{label: string, value: mixed, compareRow: array{label: string, localValue: mixed, itexiaValue: mixed, match: bool}|null, compareIndex: int|null}>
     */
    #[Computed]
    public function displayRows(): array
    {
        if ($this->itexiaData === null) {
            return [];
        }
        $labelToAttr = array_flip(SeventhingsMappingConfig::itexiaAttributes());
        $compareByAttr = [];
        foreach ($this->compareData as $idx => $row) {
            $compareByAttr[$row['itexia_attribute']] = ['row' => $row, 'index' => $idx];
        }
        $rows = [];
        foreach ($this->itexiaData as $label => $value) {
            $attr = $labelToAttr[$label] ?? null;
            $compareEntry = $attr !== null ? ($compareByAttr[$attr] ?? null) : null;
            $rows[] = [
                'label' => $label,
                'value' => $value,
                'compareRow' => $compareEntry ? $compareEntry['row'] : null,
                'compareIndex' => $compareEntry['index'] ?? null,
            ];
        }

        return $rows;
    }

    public function loadItexiaData(): void
    {
        if ($this->loaded !== null) {
            return;
        }
        if (empty(trim((string) $this->itexiaId))) {
            $this->itexiaError = 'Keine Itexia-ID (Barcode) vorhanden.';
            $this->loaded = false;

            return;
        }
        $seventhingsClass = 'Hwkdo\SeventhingsLaravel\SeventhingsLaravel';
        if (! class_exists($seventhingsClass) || ! app()->bound($seventhingsClass)) {
            $this->itexiaError = 'Das Seventhings-Paket ist nicht verfügbar.';
            $this->loaded = false;

            return;
        }
        try {
            $client = app()->make($seventhingsClass);
            $itexiaAsset = $client->findAsset($this->itexiaId);
            if ($itexiaAsset === null) {
                $this->itexiaError = 'Kein Itexia-Asset mit Barcode „'.$this->itexiaId.'“ gefunden.';
                $this->loaded = false;

                return;
            }
            $raumSoll = $itexiaAsset->raum_soll;
            $raumIst = $itexiaAsset->raum_ist;
            $this->itexiaData = [
                'Barcode' => $itexiaAsset->barcode,
                'Beschreibung' => $itexiaAsset->beschreibung,
                'Seriennummer' => $itexiaAsset->sn,
                'Preis' => $itexiaAsset->preis,
                'Kostenstelle' => $itexiaAsset->kostenstelle,
                'DATEV-Nr.' => $itexiaAsset->datev_nr,
                'Rechnungsnr.' => $itexiaAsset->rechnungsnummer,
                'Einheit' => $itexiaAsset->einheit,
                'Lieferdatum' => $itexiaAsset->lieferdatum,
                'Raum Soll' => is_object($raumSoll) ? ($raumSoll->name ?? (string) $raumSoll) : $raumSoll,
                'Raum Ist' => is_object($raumIst) ? ($raumIst->name ?? (string) $raumIst) : $raumIst,
                'Konto' => $itexiaAsset->konto,
                'Kontobeschriftung' => $itexiaAsset->kontobeschriftung,
                'Nutzungsart' => $itexiaAsset->nutzungsart,
                'Versicherungsart' => $itexiaAsset->versicherungsart,
                'Nutzungsdauer' => $itexiaAsset->nutzungsdauer,
                'Gefördert' => $itexiaAsset->gefoerdert ? 'Ja' : 'Nein',
                'Externe ID' => $itexiaAsset->external_id,
                'Halbwertszeit' => $itexiaAsset->halbwertszeit,
                'Geräteart' => $itexiaAsset->geraeteart,
            ];
            $this->itexiaArrayForMapping = SeventhingsMappingConfig::itexiaAssetToArray($itexiaAsset);
            $rawId = SeventhingsMappingConfig::getSeventhingsObjectId($itexiaAsset);
            $this->itexiaObjectId = $rawId !== null && $rawId !== '' ? (string) $rawId : null;
            $this->buildCompareData();
            $this->loaded = true;
        } catch (\Throwable $e) {
            $this->itexiaError = $e->getMessage();
            $this->loaded = false;
        }
    }

    protected function buildCompareData(): void
    {
        $this->compareData = [];
        if ($this->assetId === null) {
            return;
        }
        $asset = Asset::with(['type', 'vendor', 'owner'])->find($this->assetId);
        if (! $asset) {
            return;
        }
        $mappings = SeventhingsMapping::orderBy('sort_order')->orderBy('id')->get();
        $localLabels = SeventhingsMappingConfig::localAttributes();
        foreach ($mappings as $mapping) {
            $localValue = SeventhingsMappingConfig::getLocalValue($asset, $mapping->local_attribute);
            $itexiaValue = SeventhingsMappingConfig::getItexiaValue($this->itexiaArrayForMapping, $mapping->itexia_attribute);
            $match = SeventhingsMappingConfig::valuesMatch($localValue, $itexiaValue);
            $label = $localLabels[$mapping->local_attribute] ?? $mapping->local_attribute;
            $this->compareData[] = [
                'label' => $label,
                'local_attribute' => $mapping->local_attribute,
                'itexia_attribute' => $mapping->itexia_attribute,
                'localValue' => $localValue,
                'itexiaValue' => $itexiaValue,
                'match' => $match,
            ];
        }
    }

    public function applyFromItexia(int $rowIndex): void
    {
        $this->authorize('manage-app-assets');

        $row = $this->compareData[$rowIndex] ?? null;
        if (! $row || $row['match']) {
            return;
        }
        $asset = Asset::find($this->assetId);
        if (! $asset) {
            Flux::toast('Asset nicht gefunden.', variant: 'danger');

            return;
        }
        $localAttribute = $row['local_attribute'];
        $itexiaValue = $row['itexiaValue'] ?? '';
        if (SeventhingsMappingConfig::setLocalValue($asset, $localAttribute, $itexiaValue)) {
            $this->buildCompareData();
            $this->dispatch('asset-updated');
            Flux::toast('Wert von Seventhings wurde ins Intranet übernommen.', variant: 'success');
        } else {
            Flux::toast('Dieses Feld kann nicht von Seventhings übernommen werden.', variant: 'warning');
        }
    }

    public function applyFromIntranet(int $rowIndex): void
    {
        $this->authorize('manage-app-assets');

        $row = $this->compareData[$rowIndex] ?? null;
        if (! $row || $row['match']) {
            return;
        }
        $itexiaAttribute = $row['itexia_attribute'];
        $localValue = $row['localValue'] ?? '';
        $apiFields = SeventhingsMappingConfig::itexiaAttributeToApiField();
        $apiField = $apiFields[$itexiaAttribute] ?? null;
        if (! $apiField) {
            Flux::toast('Dieses Feld wird für Updates in Seventhings nicht unterstützt.', variant: 'warning');

            return;
        }
        $seventhingsClass = 'Hwkdo\SeventhingsLaravel\SeventhingsLaravel';
        if (! class_exists($seventhingsClass) || ! app()->bound($seventhingsClass) || $this->itexiaObjectId === null) {
            Flux::toast('Seventhings ist nicht verfügbar oder Objekt-ID fehlt.', variant: 'danger');

            return;
        }
        try {
            $client = app()->make($seventhingsClass);
            $payload = [$apiField => SeventhingsMappingConfig::normalizeValue($localValue) ?: null];
            $client->updateAsset($this->itexiaObjectId, $payload);
            $itexiaAsset = $client->findAsset($this->itexiaId);
            if ($itexiaAsset !== null) {
                $this->itexiaArrayForMapping = SeventhingsMappingConfig::itexiaAssetToArray($itexiaAsset);
            }
            $this->buildCompareData();
            $this->dispatch('asset-updated');
            Flux::toast('Wert wurde an Seventhings gesendet.', variant: 'success');
        } catch (\Throwable $e) {
            Flux::toast('Update in Seventhings fehlgeschlagen: '.$e->getMessage(), variant: 'danger');
        }
    }
};
?>

@placeholder
    <div class="rounded-xl border border-zinc-200 bg-white px-4 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
        <div class="flex items-center gap-2 text-zinc-500">
            <flux:icon.loading variant="micro" />
            <span>Itexia-Daten werden geladen…</span>
        </div>
    </div>
@endplaceholder

<div>
    <flux:card class="overflow-hidden transition-colors hover:border-zinc-400/60 dark:hover:border-zinc-500/60">
        <flux:accordion exclusive transition>
            <flux:accordion.item>
                <flux:accordion.heading class="cursor-pointer select-none font-medium">
                    Itexia-Daten (Seventhings)
                </flux:accordion.heading>
                <flux:accordion.content>
                <div wire:intersect.once="loadItexiaData" class="min-h-[2rem]">
                    @if($loaded === null)
                        <div class="flex items-center gap-2 text-zinc-500">
                            <flux:icon.loading variant="micro" wire:loading.delay.shortest />
                            <span wire:loading.delay.shortest>Lade Itexia-Daten…</span>
                        </div>
                    @elseif($itexiaError)
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:callout.text>{{ $itexiaError }}</flux:callout.text>
                            @if($this->isItexiaNotFoundError && $assetId)
                                @can('manage-app-assets')
                                    <div class="mt-3">
                                        <flux:button wire:click="openSeventhingsSearchModal" variant="outline" size="sm" icon="magnifying-glass">
                                            In Seventhings suchen
                                        </flux:button>
                                    </div>
                                @endcan
                            @endif
                        </flux:callout>
                    @elseif($itexiaData)
                        <div class="space-y-3">
                            @foreach($this->displayRows as $entry)
                                @if($entry['compareRow'] !== null)
                                    @php $row = $entry['compareRow']; $index = $entry['compareIndex']; @endphp
                                    <div @class([
                                        'rounded-lg border p-3 space-y-1.5',
                                        'border-red-200 bg-red-50/50 dark:border-red-800 dark:bg-red-900/30' => !$row['match'],
                                        'border-zinc-200 bg-zinc-50/30 dark:border-zinc-700 dark:bg-zinc-800/20' => $row['match'],
                                    ])>
                                        <div class="flex items-center justify-between gap-2">
                                            <span @class(['font-semibold text-sm', 'text-red-700 dark:text-red-200' => !$row['match']])>{{ $entry['label'] }}</span>
                                            @if($row['match'])
                                                <flux:badge size="sm" color="green" icon="check">Übereinstimmung</flux:badge>
                                            @else
                                                <flux:badge size="sm" color="red" icon="x-circle">Abweichung</flux:badge>
                                            @endif
                                        </div>
                                        <div class="grid grid-cols-1 gap-1 text-sm">
                                            <div>
                                                <span @class(['text-zinc-500 dark:text-zinc-400' => $row['match'], 'text-red-700 dark:text-red-200' => !$row['match']])>Intranet:</span>
                                                <span @class(['font-mono', 'text-red-600 dark:text-red-100' => !$row['match']])>{{ $row['localValue'] ?? '—' }}</span>
                                            </div>
                                            <div>
                                                <span @class(['text-zinc-500 dark:text-zinc-400' => $row['match'], 'text-red-700 dark:text-red-200' => !$row['match']])>Seventhings:</span>
                                                <span @class(['font-mono', 'text-red-600 dark:text-red-100' => !$row['match']])>{{ $row['itexiaValue'] ?? '—' }}</span>
                                            </div>
                                        </div>
                                        @if(!$row['match'])
                                            @can('manage-app-assets')
                                                <div class="mt-2 flex flex-wrap gap-1" wire:loading.class="opacity-60">
                                                    <flux:tooltip content="Von Seventhings zum Intranet übernehmen" position="top">
                                                        <flux:button
                                                            wire:click="applyFromItexia({{ $index }})"
                                                            wire:loading.attr="disabled"
                                                            size="sm"
                                                            variant="ghost"
                                                            icon="arrow-left"
                                                            icon:variant="micro"
                                                        />
                                                    </flux:tooltip>
                                                    <flux:tooltip content="Vom Intranet zu Seventhings übernehmen" position="top">
                                                        <flux:button
                                                            wire:click="applyFromIntranet({{ $index }})"
                                                            wire:loading.attr="disabled"
                                                            size="sm"
                                                            variant="ghost"
                                                            icon="arrow-right"
                                                            icon:variant="micro"
                                                        />
                                                    </flux:tooltip>
                                                </div>
                                            @endcan
                                        @endif
                                    </div>
                                @else
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                                        <dt class="font-semibold">{{ $entry['label'] }}</dt>
                                        <dd class="font-mono">{{ $entry['value'] ?? '—' }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
                </flux:accordion.content>
            </flux:accordion.item>
        </flux:accordion>
    </flux:card>

    {{-- Seventhings-Suche nach Seriennummer (wenn Itexia-ID nicht gefunden) --}}
    <flux:modal wire:model="showSeventhingsSearchModal" class="md:max-w-2xl" variant="flyout">
        <div class="space-y-4">
            <flux:heading size="lg">In Seventhings suchen</flux:heading>
            <p class="text-sm text-zinc-500">Seriennummer eingeben oder den Vorschlag aus dem Asset nutzen. Gefundenes Objekt prüfen und bei Bedarf als Itexia-ID (Barcode) übernehmen.</p>
            @if($assetId)
                @livewire('intranet-app-assets::apps.assets.seventhings-serial-search', ['assetId' => $assetId], key('seventhings-serial-'.$assetId))
            @endif
            <div class="flex justify-end pt-2">
                <flux:button wire:click="closeSeventhingsSearchModal" variant="ghost">Schließen</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
