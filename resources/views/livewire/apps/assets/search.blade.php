<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Asset-Suche')] class extends Component
{
    #[Url(as: 'q')]
    public string $searchQuery = '';

    #[Computed]
    public function resultCards(): \Illuminate\Support\Collection
    {
        $query = trim($this->searchQuery);
        if ($query === '' || mb_strlen($query) < 2) {
            return collect();
        }

        $search = Asset::search($query)->options([
            'highlight_full_fields' => 'name,model,serial_number,owner_name,type_name,vendor_name,location,order_number,invoice_number,history_text,notes_text',
            'highlight_affix_num_tokens' => 6,
        ]);

        $raw = $search->raw();
        $assets = $search
            ->query(fn ($builder) => $builder->with(['owner', 'type', 'vendor']))
            ->take(50)
            ->get();

        $highlightsById = $this->extractHighlightsByAssetId($raw);

        return $assets->map(function (Asset $asset) use ($query, $highlightsById): array {
            $highlight = $highlightsById[(int) $asset->id] ?? ['fields' => [], 'snippets' => []];

            $fieldValues = [
                'name' => $asset->display_name,
                'serial_number' => (string) $asset->serial_number,
                'owner_name' => (string) ($asset->owner?->name ?? '—'),
                'type_name' => (string) ($asset->type?->name ?? '—'),
                'vendor_name' => (string) ($asset->vendor?->name ?? '—'),
                'location' => (string) ($asset->location ?? '—'),
                'order_number' => (string) ($asset->order_number ?? ''),
                'invoice_number' => (string) ($asset->invoice_number ?? ''),
            ];

            $labels = [
                'name' => 'Name / Modell',
                'serial_number' => 'Seriennummer',
                'owner_name' => 'Besitzer',
                'type_name' => 'Typ',
                'vendor_name' => 'Hersteller',
                'location' => 'Standort',
                'order_number' => 'Bestellnummer',
                'invoice_number' => 'Rechnungsnummer',
            ];

            $fieldMatches = collect($labels)->map(function (string $label, string $field) use ($fieldValues, $highlight, $query): ?array {
                $value = $fieldValues[$field] ?? '';
                if ($value === '' || $value === '—') {
                    return null;
                }

                if (isset($highlight['fields'][$field])) {
                    return [
                        'label' => $label,
                        'snippet' => $highlight['fields'][$field],
                    ];
                }

                if (Str::contains(mb_strtolower($value), mb_strtolower($query))) {
                    return [
                        'label' => $label,
                        'snippet' => $this->fallbackHighlight($value, $query),
                    ];
                }

                return null;
            })->filter()->values();

            return [
                'asset' => $asset,
                'fieldMatches' => $fieldMatches,
                'snippets' => collect($highlight['snippets'] ?? [])->take(2)->values(),
            ];
        });
    }

    /**
     * @return array<int, array{fields: array<string, string>, snippets: array<int, string>}>
     */
    private function extractHighlightsByAssetId(array $raw): array
    {
        $hits = $raw['hits'] ?? [];
        $result = [];

        foreach ($hits as $hit) {
            $document = $hit['document'] ?? [];
            $id = isset($document['id']) ? (int) $document['id'] : null;
            if ($id === null) {
                continue;
            }

            $fields = [];
            $snippets = [];
            $highlights = $hit['highlights'] ?? $hit['highlight'] ?? [];

            if (is_array($highlights)) {
                foreach ($highlights as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    $field = (string) ($entry['field'] ?? '');
                    $snippet = (string) ($entry['snippet'] ?? '');

                    if ($snippet === '' && isset($entry['snippets']) && is_array($entry['snippets']) && $entry['snippets'] !== []) {
                        $snippet = (string) $entry['snippets'][0];
                    }

                    if ($field === '' || $snippet === '') {
                        continue;
                    }

                    $safeSnippet = strip_tags($snippet, '<mark>');
                    $fields[$field] = $safeSnippet;

                    if (in_array($field, ['history_text', 'notes_text'], true)) {
                        $snippets[] = $safeSnippet;
                    }
                }
            }

            $result[$id] = [
                'fields' => $fields,
                'snippets' => array_values(array_unique($snippets)),
            ];
        }

        return $result;
    }

    private function fallbackHighlight(string $text, string $query): string
    {
        $escapedText = e($text);
        $escapedQuery = preg_quote($query, '/');

        return (string) preg_replace('/('.$escapedQuery.')/iu', '<mark>$1</mark>', $escapedText);
    }
};
?>

<div>
    <x-intranet-app-assets::assets-layout heading="Asset-Suche" subheading="Suche über Asset-Daten, Verlauf und Notizen">
        <div class="space-y-4">
            <div class="glass-card sticky top-3 z-20" style="--glass-opacity: 0.92; overflow: visible">
                <div class="flex items-center gap-3 border-b border-[#d0e3f9] px-5 py-4 dark:border-white/10">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                        <flux:icon icon="magnifying-glass" variant="micro" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-[#073070] dark:text-white">Assets durchsuchen</h2>
                        <p class="text-xs text-slate-600 dark:text-white/70">Kernfelder, Verlauf und Notizen.</p>
                    </div>
                </div>
                <div class="p-4">
                    <flux:input
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Name, Seriennummer, Besitzer, Notiztext ..."
                        icon="magnifying-glass"
                        class="w-full"
                        aria-label="Assets durchsuchen"
                    />
                </div>
            </div>

            @if (trim($searchQuery) !== '' && mb_strlen(trim($searchQuery)) < 2)
                <x-intranet-app-assets::hwk-empty icon="magnifying-glass" title="Suche starten">
                    Geben Sie mindestens 2 Zeichen ein, um die Suche zu starten.
                </x-intranet-app-assets::hwk-empty>
            @endif

            @if (!empty(trim($searchQuery)) && mb_strlen(trim($searchQuery)) >= 2)
                <div class="glass-card flex items-center gap-2 px-5 py-3" style="--glass-opacity: 0.92">
                    <flux:heading size="md">Treffer</flux:heading>
                    <x-intranet-app-assets::hwk-badge variant="outline">{{ $this->resultCards->count() }}</x-intranet-app-assets::hwk-badge>
                </div>

                @if($this->resultCards->isEmpty())
                    <x-intranet-app-assets::hwk-empty icon="magnifying-glass" title="Keine Treffer">
                        Keine Treffer gefunden. Bitte versuchen Sie einen anderen Suchbegriff.
                    </x-intranet-app-assets::hwk-empty>
                @else
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        @foreach($this->resultCards as $card)
                            @php
                                /** @var \Hwkdo\IntranetAppAssets\Models\Asset $asset */
                                $asset = $card['asset'];
                            @endphp
                            <section wire:key="asset-search-result-{{ $asset->id }}" class="glass-card overflow-hidden space-y-3 p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:heading size="sm">{{ $asset->display_name }}</flux:heading>
                                            @if($asset->configmgrSerialMismatches())
                                                <x-intranet-app-assets::hwk-badge color="amber" size="sm">SN weicht ab</x-intranet-app-assets::hwk-badge>
                                            @endif
                                        </div>
                                        <flux:text class="text-sm text-slate-600 dark:text-white/70">
                                            {{ $asset->type?->name ?? '—' }} · {{ $asset->vendor?->name ?? '—' }}
                                        </flux:text>
                                    </div>
                                    <flux:button href="{{ route('apps.assets.show', array_filter(['asset' => $asset, 'from' => 'search', 'sq' => filled(trim($this->searchQuery)) ? trim($this->searchQuery) : null], fn ($v) => $v !== null && $v !== '')) }}" size="sm" variant="ghost" icon="eye">
                                        Öffnen
                                    </flux:button>
                                </div>

                                <div class="space-y-1 text-sm">
                                    <div><span class="font-medium">Seriennummer:</span> {{ $asset->serial_number }}</div>
                                    @if($asset->configmgrSerialMismatches())
                                        <div>
                                            <span class="font-medium">SCCM-Seriennummer:</span>
                                            <span class="font-mono">{{ $asset->configmgr_serial_number }}</span>
                                        </div>
                                    @endif
                                    <div><span class="font-medium">Besitzer:</span> {{ $asset->owner?->name ?? '—' }}</div>
                                    <div><span class="font-medium">Standort:</span> {{ $asset->location ?? '—' }}</div>
                                </div>

                                @if(collect($card['fieldMatches'])->isNotEmpty())
                                    <div class="space-y-2 rounded-lg bg-[#d0e3f9]/50 p-3 text-sm dark:bg-white/10">
                                        <div class="font-medium text-[#073070] dark:text-white">Treffer in Asset-Feldern</div>
                                        @foreach($card['fieldMatches'] as $match)
                                            <div>
                                                <span class="font-medium">{{ $match['label'] }}:</span>
                                                {!! $match['snippet'] !!}
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if(collect($card['snippets'])->isNotEmpty())
                                    <div class="space-y-2 rounded-lg bg-[#a0c7f2]/35 p-3 text-sm dark:bg-white/10">
                                        <div class="font-medium text-[#073070] dark:text-white">Treffer in Verlauf / Notizen</div>
                                        @foreach($card['snippets'] as $snippet)
                                            <div>{!! $snippet !!}</div>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
