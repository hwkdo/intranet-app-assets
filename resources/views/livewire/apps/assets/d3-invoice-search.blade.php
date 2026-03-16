<?php

use Flux\Flux;
use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $assetId = null;

    public string $search = '';

    /** @var array<int, array{id: string, link: string, caption: string}> */
    public array $results = [];

    public ?string $searchError = null;

    public bool $initialSearchDone = false;

    /**
     * Vorgeschlagene Suchbegriffe aus dem Asset (Reihenfolge: BEN, Seriennummer, IMEI, MAC).
     *
     * @return array<int, array{label: string, value: string}>
     */
    #[Computed]
    public function suggestedSearchTerms(): array
    {
        if ($this->assetId === null) {
            return [];
        }
        $asset = Asset::find($this->assetId);
        if (! $asset) {
            return [];
        }
        $terms = [];
        $candidates = [
            'BEN' => $asset->order_number ?? null,
            'Seriennummer' => $asset->serial_number ?? null,
            'IMEI' => $asset->imei ?? null,
            'MAC' => $asset->mac ?? null,
        ];
        foreach ($candidates as $label => $value) {
            if (filled($value)) {
                $terms[] = ['label' => $label, 'value' => trim((string) $value)];
            }
        }
        if (filled($asset->invoice_number)) {
            $terms[] = [
                'label' => 'Rechnungsnr. (aktuell)',
                'value' => trim((string) $asset->invoice_number),
            ];
        }

        return $terms;
    }

    public function useSuggestedTerm(string $value): void
    {
        $this->search = $value;
        $this->runSearch();
    }

    public function mount(?int $assetId = null): void
    {
        $this->assetId = $assetId;
        if ($this->assetId !== null) {
            $asset = Asset::find($this->assetId);
            if ($asset) {
                $suggested = $this->suggestedSearchTerms;
                $term = $suggested[0]['value'] ?? $asset->order_number ?? $asset->serial_number ?? $asset->imei ?? '';
                $this->search = trim((string) $term);
                if ($this->search !== '') {
                    $this->runSearch();
                }
            }
            $this->initialSearchDone = true;
        }
    }

    public function runSearch(): void
    {
        $this->searchError = null;
        $this->results = [];
        $term = trim($this->search);
        if ($term === '') {
            return;
        }
        if (! class_exists(D3Client::class)) {
            $this->searchError = 'D3-REST-Paket ist nicht verfügbar.';

            return;
        }
        try {
            $client = app(D3Client::class);
            $raw = $client->SearchResult($term, DocTypeEnum::Zahlungsbeleg, null, 200, true);
            $items = $raw['items'] ?? [];
            $baseUrl = rtrim(D3Client::getBaseUrl(), '/');
            foreach ($items as $item) {
                $id = $item['id'] ?? null;
                $href = $item['_links']['details']['href'] ?? null;
                if ($id && $href) {
                    $link = $baseUrl.'/'.ltrim($href, '/');
                    $this->results[] = [
                        'id' => $id,
                        'link' => $link,
                        'caption' => $item['caption'] ?? $id,
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->searchError = 'Suche fehlgeschlagen: '.$e->getMessage();
        }
    }

    public function setAsInvoiceNumber(string $documentId): void
    {
        if ($this->assetId === null) {
            return;
        }
        $this->authorize('manage-app-assets');
        $asset = Asset::find($this->assetId);
        if (! $asset) {
            Flux::toast('Asset nicht gefunden.', variant: 'danger');

            return;
        }
        $asset->invoice_number = $documentId;
        $asset->save();
        $asset->historyEntries()->create([
            'event' => AssetHistory::EventUpdated,
            'user_id' => auth()->id(),
        ]);
        $this->dispatch('invoice-number-set');
        Flux::toast('Rechnungsnummer wurde übernommen.', variant: 'success');
    }
}; ?>
<div class="space-y-4">
    <flux:field>
        <flux:label>Suchbegriff</flux:label>
        <div class="flex gap-2">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="BEN, Seriennummer, IMEI, MAC…"
                class="flex-1"
            />
            <flux:button wire:click="runSearch" variant="primary" icon="magnifying-glass">
                Suchen
            </flux:button>
        </div>
        @if($assetId && count($this->suggestedSearchTerms) > 0)
            <flux:description class="mt-2">
                Vorschläge aus diesem Asset:
                <span class="inline-flex flex-wrap gap-1.5 mt-1">
                    @foreach($this->suggestedSearchTerms as $suggestion)
                        <flux:button
                            wire:click="useSuggestedTerm({{ json_encode($suggestion['value']) }})"
                            variant="outline"
                            size="sm"
                            class="!text-xs"
                        >
                            {{ $suggestion['label'] }}: <span class="font-mono">{{ $suggestion['value'] }}</span>
                        </flux:button>
                    @endforeach
                </span>
            </flux:description>
        @endif
    </flux:field>

    @if($searchError)
        <flux:callout variant="danger" icon="exclamation-triangle">
            {{ $searchError }}
        </flux:callout>
    @endif

    @if(count($results) > 0)
        <div class="space-y-2">
            <flux:heading size="sm">Treffer ({{ count($results) }})</flux:heading>
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-700 max-h-96 overflow-y-auto">
                @foreach($results as $idx => $doc)
                    <li wire:key="d3-doc-{{ $doc['id'] }}" class="flex items-center justify-between gap-3 px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <div class="min-w-0 flex-1">
                            <a
                                href="{{ $doc['link'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium text-flux-primary hover:underline truncate block"
                            >
                                {{ $doc['caption'] ?: $doc['id'] }}
                            </a>
                            <span class="text-xs font-mono text-zinc-500">{{ $doc['id'] }}</span>
                        </div>
                        @if($assetId)
                            <flux:button
                                wire:click="setAsInvoiceNumber({{ json_encode($doc['id']) }})"
                                variant="primary"
                                size="sm"
                            >
                                Als Rechnungsnr. übernehmen
                            </flux:button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif($initialSearchDone && $search !== '' && !$searchError)
        <flux:text class="text-zinc-500">Keine Dokumente gefunden. Anderen Suchbegriff versuchen.</flux:text>
    @endif
</div>
