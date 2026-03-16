<?php

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\SeventhingsMappingConfig;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public const SearchTypeSn = 'sn';

    public const SearchTypeRechnungsnummer = 'rechnungsnummer';

    public ?int $assetId = null;

    /** Suchart: 'sn' = Seriennummer, 'rechnungsnummer' = Rechnungsnr. */
    public string $searchType = 'sn';

    public string $search = '';

    /** @var array{ barcode: string, beschreibung: string, sn: string, rechnungsnummer: string, object_id: string|null }|null */
    public ?array $result = null;

    public ?string $searchError = null;

    public bool $initialSearchDone = false;

    /** true wenn die letzte Suche per Rechnungsnummer lief (für Meldung „nichts gefunden“). */
    public bool $lastSearchByRechnungsnummer = false;

    #[Computed]
    public function suggestedSearchTerm(): ?array
    {
        if ($this->assetId === null) {
            return null;
        }
        $asset = Asset::find($this->assetId);
        if (! $asset || empty(trim((string) $asset->serial_number))) {
            return null;
        }
        $value = trim((string) $asset->serial_number);

        return ['label' => 'Seriennummer', 'value' => $value];
    }

    #[Computed]
    public function suggestedInvoiceTerm(): ?array
    {
        if ($this->assetId === null) {
            return null;
        }
        $asset = Asset::find($this->assetId);
        if (! $asset || empty(trim((string) $asset->invoice_number))) {
            return null;
        }
        $value = trim((string) $asset->invoice_number);

        return ['label' => 'Rechnungsnr.', 'value' => $value];
    }

    /** Für Treffer-Aktion: ob lokale Itexia-ID gesetzt werden kann und Anzeige-Wert. */
    #[Computed]
    public function localItexiaIdForSeventhings(): array
    {
        if ($this->assetId === null) {
            return ['can_set' => false, 'local_itexia_id' => ''];
        }
        $asset = Asset::find($this->assetId);
        if (! $asset) {
            return ['can_set' => false, 'local_itexia_id' => ''];
        }
        $id = trim((string) $asset->itexia_id);

        return ['can_set' => $id !== '', 'local_itexia_id' => $id];
    }

    public function useSuggestedTerm(string $value): void
    {
        $this->searchType = self::SearchTypeSn;
        $this->search = $value;
        $this->runSearchByType();
    }

    public function useSuggestedInvoiceTerm(string $value): void
    {
        $this->searchType = self::SearchTypeRechnungsnummer;
        $this->search = $value;
        $this->runSearchByType();
    }

    public function mount(?int $assetId = null): void
    {
        $this->assetId = $assetId;
        if ($this->assetId !== null) {
            $suggestion = $this->suggestedSearchTerm;
            if ($suggestion !== null) {
                $this->searchType = self::SearchTypeSn;
                $this->search = $suggestion['value'];
                $this->runSearchByType();
            }
            $this->initialSearchDone = true;
        }
    }

    /** Führt die Suche gemäß aktueller searchType aus (Seriennummer oder Rechnungsnr.). */
    public function runSearchByType(): void
    {
        $this->lastSearchByRechnungsnummer = $this->searchType === self::SearchTypeRechnungsnummer;
        $this->searchError = null;
        $this->result = null;
        $term = trim($this->search);
        if ($term === '') {
            return;
        }
        $seventhingsClass = 'Hwkdo\SeventhingsLaravel\SeventhingsLaravel';
        if (! class_exists($seventhingsClass) || ! app()->bound($seventhingsClass)) {
            $this->searchError = 'Das Seventhings-Paket ist nicht verfügbar.';

            return;
        }
        try {
            $client = app()->make($seventhingsClass);
            $itexiaAsset = $this->searchType === self::SearchTypeRechnungsnummer
                ? $client->findAssetByRechnungsnummer($term)
                : $client->findAssetBySn($term);
            if ($itexiaAsset !== null) {
                $this->result = $this->buildResultFromItexiaAsset($itexiaAsset);
            }
        } catch (\Throwable $e) {
            $this->searchError = 'Suche fehlgeschlagen: '.$e->getMessage();
        }
    }

    /**
     * @param  \Hwkdo\SeventhingsLaravel\Models\Asset  $itexiaAsset
     * @return array{ barcode: string, beschreibung: string, sn: string, rechnungsnummer: string, object_id: string|null }
     */
    private function buildResultFromItexiaAsset($itexiaAsset): array
    {
        $objectId = SeventhingsMappingConfig::getSeventhingsObjectId($itexiaAsset);

        return [
            'barcode' => $itexiaAsset->barcode ?? '',
            'beschreibung' => $itexiaAsset->beschreibung ?? '',
            'sn' => $itexiaAsset->sn ?? '',
            'rechnungsnummer' => $itexiaAsset->rechnungsnummer ?? '',
            'object_id' => $objectId !== null && $objectId !== '' ? (string) $objectId : null,
        ];
    }

    /**
     * Trägt die lokale Itexia-ID des Assets als Barcode beim gefundenen Seventhings-Objekt ein.
     */
    public function setBarcodeInSeventhings(): void
    {
        if ($this->assetId === null || $this->result === null) {
            return;
        }
        $this->authorize('manage-app-assets');
        $asset = Asset::find($this->assetId);
        if (! $asset) {
            Flux::toast('Asset nicht gefunden.', variant: 'danger');

            return;
        }
        $localItexiaId = trim((string) $asset->itexia_id);
        if ($localItexiaId === '') {
            Flux::toast('Lokale Itexia-ID ist leer. Bitte zuerst beim Asset pflegen.', variant: 'warning');

            return;
        }
        $objectId = $this->result['object_id'] ?? null;
        if ($objectId === null || $objectId === '') {
            Flux::toast('Objekt-ID des Treffers konnte nicht ermittelt werden.', variant: 'danger');

            return;
        }
        $seventhingsClass = 'Hwkdo\SeventhingsLaravel\SeventhingsLaravel';
        if (! class_exists($seventhingsClass) || ! app()->bound($seventhingsClass)) {
            Flux::toast('Seventhings ist nicht verfügbar.', variant: 'danger');

            return;
        }
        try {
            $client = app()->make($seventhingsClass);
            $client->updateAsset($objectId, ['barcode' => $localItexiaId]);
            $this->dispatch('asset-updated');
            Flux::toast('Barcode in Seventhings wurde auf die lokale Itexia-ID gesetzt.', variant: 'success');
            $this->result['barcode'] = $localItexiaId;
        } catch (\Throwable $e) {
            Flux::toast('Update in Seventhings fehlgeschlagen: '.$e->getMessage(), variant: 'danger');
        }
    }
}; ?>
<div class="space-y-4">
    <flux:field>
        <flux:label>Suchart</flux:label>
        <flux:select wire:model.live="searchType" class="w-full max-w-xs">
            <flux:select.option value="sn">Seriennummer</flux:select.option>
            <flux:select.option value="rechnungsnummer">Rechnungsnr.</flux:select.option>
        </flux:select>
    </flux:field>

    <flux:field>
        <flux:label>{{ $searchType === 'rechnungsnummer' ? 'Rechnungsnummer' : 'Seriennummer' }}</flux:label>
        <div class="flex gap-2">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ $searchType === 'rechnungsnummer' ? 'Rechnungsnummer aus Seventhings suchen…' : 'Seriennummer aus Seventhings suchen…' }}"
                class="flex-1"
            />
            <flux:button wire:click="runSearchByType" variant="primary" icon="magnifying-glass">
                Suchen
            </flux:button>
        </div>
        @if($assetId && ($this->suggestedSearchTerm !== null || $this->suggestedInvoiceTerm !== null))
            <flux:description class="mt-2">
                Vorschläge aus diesem Asset (setzen Suchart und Wert):
                <span class="inline-flex flex-wrap gap-1.5 mt-1">
                    @if($this->suggestedSearchTerm !== null)
                        <flux:button
                            wire:click="useSuggestedTerm({{ json_encode($this->suggestedSearchTerm['value']) }})"
                            variant="outline"
                            size="sm"
                            class="!text-xs"
                        >
                            Seriennummer: <span class="font-mono">{{ $this->suggestedSearchTerm['value'] }}</span>
                        </flux:button>
                    @endif
                    @if($this->suggestedInvoiceTerm !== null)
                        <flux:button
                            wire:click="useSuggestedInvoiceTerm({{ json_encode($this->suggestedInvoiceTerm['value']) }})"
                            variant="outline"
                            size="sm"
                            class="!text-xs"
                        >
                            Rechnungsnr.: <span class="font-mono">{{ $this->suggestedInvoiceTerm['value'] }}</span>
                        </flux:button>
                    @endif
                </span>
            </flux:description>
        @endif
    </flux:field>

    @if($searchError)
        <flux:callout variant="danger" icon="exclamation-triangle">
            {{ $searchError }}
        </flux:callout>
    @endif

    @if($result !== null)
        <div class="space-y-2">
            <flux:heading size="sm">Treffer in Seventhings</flux:heading>
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 space-y-2">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                    <span class="font-semibold">Barcode (Itexia-ID)</span>
                    <span class="font-mono">{{ $result['barcode'] }}</span>
                </div>
                @if($result['beschreibung'] !== '')
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                        <span class="font-semibold">Beschreibung</span>
                        <span>{{ $result['beschreibung'] }}</span>
                    </div>
                @endif
                @if($result['sn'] !== '')
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                        <span class="font-semibold">Seriennummer</span>
                        <span class="font-mono">{{ $result['sn'] }}</span>
                    </div>
                @endif
                @if(isset($result['rechnungsnummer']) && $result['rechnungsnummer'] !== '')
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                        <span class="font-semibold">Rechnungsnr.</span>
                        <span class="font-mono">{{ $result['rechnungsnummer'] }}</span>
                    </div>
                @endif
                @if($assetId && isset($result['object_id']) && $result['object_id'] !== '')
                    @php $localInfo = $this->localItexiaIdForSeventhings; @endphp
                    <div class="pt-2 space-y-2">
                        @if($localInfo['can_set'])
                            <flux:button
                                wire:click="setBarcodeInSeventhings"
                                wire:loading.attr="disabled"
                                variant="primary"
                                size="sm"
                            >
                                Lokale Itexia-ID in Seventhings eintragen
                            </flux:button>
                            <flux:description class="!mt-1">Setzt den Barcode des gefundenen Objekts auf „{{ $localInfo['local_itexia_id'] }}“ (Itexia-ID dieses Assets).</flux:description>
                        @else
                            <flux:text class="text-amber-600 dark:text-amber-400 text-sm">Lokale Itexia-ID ist leer – bitte zuerst beim Asset unter Stammdaten eintragen, dann hier erneut ausführen.</flux:text>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @elseif($initialSearchDone && $search !== '' && !$searchError)
        <flux:text class="text-zinc-500">
            @if($lastSearchByRechnungsnummer)
                Kein Objekt mit dieser Rechnungsnummer in Seventhings gefunden.
            @else
                Kein Objekt mit dieser Seriennummer in Seventhings gefunden.
            @endif
        </flux:text>
    @endif
</div>
