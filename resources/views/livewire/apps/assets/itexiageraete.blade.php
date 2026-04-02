<?php

use Hwkdo\IntranetAppAssets\Exports\AssetsTableExport;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Support\DmsLinkHelper;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')] #[Title('Itexia-Geräte')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var 'all'|'found'|'not-found' */
    #[Url]
    public string $itexiaFilter = 'all';

    /** @var 'all'|'without'|'with' */
    #[Url]
    public string $invoiceOrderFilter = 'all';

    /** @var 'all'|'with-room'|'without-room' */
    #[Url(as: 'itexiaRoom')]
    public string $itexiaRoomFilter = 'all';

    /** @var list<int|string> */
    #[Url(as: 'types')]
    public array $typeIds = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedItexiaFilter(): void
    {
        $this->resetPage();
    }

    public function updatedInvoiceOrderFilter(): void
    {
        $this->resetPage();
    }

    public function updatedItexiaRoomFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeIds(): void
    {
        $this->resetPage();
    }

    /**
     * @return list<int>
     */
    private function validatedTypeIdsForQuery(): array
    {
        $allowed = AssetType::query()->pluck('id')->map(fn (int|string $id): int => (int) $id)->all();
        $selected = array_values(array_unique(array_filter(array_map('intval', $this->typeIds))));

        return array_values(array_intersect($selected, $allowed));
    }

    protected function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Asset::query()
            ->with(['type', 'vendor', 'owner'])
            ->whereNotNull('itexia_id')
            ->where('itexia_id', '!=', '')
            ->orderBy('model');
    }

    protected function applyFilters(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $searchTerm = trim($this->search ?? '');
        $typeIds = $this->validatedTypeIdsForQuery();

        return $query
            ->when($searchTerm !== '', function ($q) use ($searchTerm) {
                $term = '%'.$searchTerm.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('serial_number', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('itexia_id', 'like', $term)
                        ->orWhere('itexia_uuid', 'like', $term)
                        ->orWhere('invoice_number', 'like', $term)
                        ->orWhere('order_number', 'like', $term)
                        ->orWhereHas('type', fn ($t) => $t->where('name', 'like', $term))
                        ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $term))
                        ->orWhereHas('owner', fn ($o) => $o->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term));
                });
            })
            ->when($this->itexiaFilter === 'found', fn ($q) => $q->whereNotNull('itexia_uuid')->where('itexia_uuid', '!=', ''))
            ->when($this->itexiaFilter === 'not-found', fn ($q) => $q->where(function ($q) {
                $q->whereNull('itexia_uuid')->orWhere('itexia_uuid', '');
            }))
            ->when($this->invoiceOrderFilter === 'without', fn ($q) => $q->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereNull('invoice_number')->orWhere('invoice_number', '');
                })->where(function ($q) {
                    $q->whereNull('order_number')->orWhere('order_number', '');
                });
            }))
            ->when($this->invoiceOrderFilter === 'with', fn ($q) => $q->where(function ($q) {
                $q->whereNotNull('invoice_number')->where('invoice_number', '!=', '')
                    ->orWhereNotNull('order_number')->where('order_number', '!=', '');
            }))
            ->when($this->itexiaRoomFilter === 'with-room', fn ($q) => $q->where(function ($q) {
                $q->whereNotNull('itexia_actual_room_id')
                    ->orWhereNotNull('itexia_target_room_id');
            }))
            ->when($this->itexiaRoomFilter === 'without-room', fn ($q) => $q
                ->whereNull('itexia_actual_room_id')
                ->whereNull('itexia_target_room_id'))
            ->when($typeIds !== [], fn ($q) => $q->whereIn('asset_type_id', $typeIds));
    }

    #[Computed]
    public function assetTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return AssetType::allOrdered();
    }

    #[Computed]
    public function assets(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->applyFilters($this->baseQuery())->paginate(25);
    }

    #[Computed]
    public function dmsBaseUrl(): string
    {
        $fromSettings = trim(IntranetAppAssetsSettings::current()?->settings?->dmsBaseUrl ?? '');

        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return DmsLinkHelper::baseUrlFromDmsSearchUrl(config('d3-rest-laravel.dms-search-url', ''));
    }

    public function invoiceNumberLink(?string $number): ?string
    {
        return DmsLinkHelper::invoiceUrl($this->dmsBaseUrl, $number);
    }

    public function orderNumberLink(?string $number): ?string
    {
        return DmsLinkHelper::orderNumberUrl($this->dmsBaseUrl, $number);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    public function getExportQueryAll(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->baseQuery();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    public function getExportQueryFiltered(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->applyFilters($this->baseQuery());
    }

    /**
     * @return array<int, array{heading: string, value: callable(Asset): string|int|null}>
     */
    public function getExportColumns(): array
    {
        return [
            ['heading' => 'Modell / Name', 'value' => fn (Asset $a) => $a->display_name],
            ['heading' => 'Seriennummer', 'value' => fn (Asset $a) => $a->serial_number],
            ['heading' => 'Itexia-ID', 'value' => fn (Asset $a) => $a->itexia_id ?? '—'],
            ['heading' => 'Itexia-UUID', 'value' => fn (Asset $a) => $a->itexia_uuid ?? '—'],
            ['heading' => 'Rechnungsnr.', 'value' => fn (Asset $a) => $a->invoice_number ?? '—'],
            ['heading' => 'BEN', 'value' => fn (Asset $a) => $a->order_number ?? '—'],
            ['heading' => 'Typ', 'value' => fn (Asset $a) => $a->type?->name ?? '—'],
            ['heading' => 'Hersteller', 'value' => fn (Asset $a) => $a->vendor?->name ?? '—'],
            ['heading' => 'Besitzer', 'value' => fn (Asset $a) => $a->owner?->name ?? '—'],
            ['heading' => 'Status', 'value' => function (Asset $a) {
                $status = [];
                if ($a->itexia_uuid) {
                    $status[] = 'Gefunden';
                } else {
                    $status[] = 'Nicht gefunden';
                }
                if ($a->is_missing) {
                    $status[] = 'Vermisst';
                }
                if ($a->is_clarification) {
                    $status[] = 'Klärung';
                }
                return implode(', ', $status);
            }],
        ];
    }

    public function getExportFilename(string $mode): string
    {
        return $mode === 'all' ? 'itexiageraete-alle.xlsx' : 'itexiageraete-gefiltert.xlsx';
    }

    public function exportExcelAll(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(
            new AssetsTableExport($this->getExportQueryAll(), $this->getExportColumns()),
            $this->getExportFilename('all')
        );
    }

    public function exportExcelFiltered(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(
            new AssetsTableExport($this->getExportQueryFiltered(), $this->getExportColumns()),
            $this->getExportFilename('filtered')
        );
    }
}; ?>
<div>
<x-intranet-app-assets::assets-layout heading="Itexia-Geräte" subheading="Assets mit Itexia-ID (Barcode) und Seventhings-UUID">
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 flex-wrap items-center gap-3">
                <div class="min-w-64 max-w-sm flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suchen in allen Spalten…"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>
                <flux:pillbox
                    wire:model.live="typeIds"
                    multiple
                    searchable
                    search:placeholder="Typ suchen…"
                    placeholder="Typen filtern…"
                    size="sm"
                    class="min-w-52 max-w-md shrink-0 [&_[data-flux-pillbox-placeholder]]:text-zinc-700 dark:[&_[data-flux-pillbox-placeholder]]:text-zinc-300"
                >
                    @foreach($this->assetTypes as $type)
                        <flux:pillbox.option value="{{ $type->id }}">{{ $type->name }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
                <flux:select wire:model.live="itexiaFilter" placeholder="Itexia-Filter" class="w-52 shrink-0">
                    <flux:select.option value="all">Alle Itexia-Geräte</flux:select.option>
                    <flux:select.option value="found">Gefunden in Itexia</flux:select.option>
                    <flux:select.option value="not-found">Nicht gefunden in Itexia</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="invoiceOrderFilter" placeholder="Rechnung/Bestellung" class="w-52 shrink-0">
                    <flux:select.option value="all">Alle</flux:select.option>
                    <flux:select.option value="without">Ohne Rechnung/Bestellung</flux:select.option>
                    <flux:select.option value="with">Mit Rechnung/Bestellung</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="itexiaRoomFilter" placeholder="Itexia-Raum" class="w-52 shrink-0">
                    <flux:select.option value="all">Alle</flux:select.option>
                    <flux:select.option value="with-room">Nur mit Raum</flux:select.option>
                    <flux:select.option value="without-room">Nur ohne Raum</flux:select.option>
                </flux:select>
            </div>
            <flux:dropdown position="bottom" align="end">
                <flux:button variant="ghost" icon="arrow-down-tray" icon-trailing="chevron-down" wire:loading.attr="disabled">
                    Excel-Export
                </flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportExcelAll" icon="document-duplicate">Alle Daten exportieren</flux:menu.item>
                    <flux:menu.item wire:click="exportExcelFiltered" icon="funnel">Gefilterte Daten exportieren</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Modell / Name</flux:table.column>
                <flux:table.column>Seriennummer</flux:table.column>
                <flux:table.column>Itexia-ID</flux:table.column>
                <flux:table.column>Itexia-UUID</flux:table.column>
                <flux:table.column>Rechnungsnr.</flux:table.column>
                <flux:table.column>BEN</flux:table.column>
                <flux:table.column>Typ</flux:table.column>
                <flux:table.column>Hersteller</flux:table.column>
                <flux:table.column>Besitzer</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->assets as $asset)
                    <flux:table.row wire:key="itexia-{{ $asset->id }}">
                        <flux:table.cell>
                            <div class="font-medium">{{ $asset->display_name }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $asset->serial_number }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $asset->itexia_id ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm max-w-[6rem]">
                            @php $uuid = $asset->itexia_uuid ?? '—'; @endphp
                            <flux:tooltip :content="$uuid" position="top">
                                <span class="block truncate">{{ $uuid }}</span>
                            </flux:tooltip>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">
                            @php $invoiceLink = $this->invoiceNumberLink($asset->invoice_number); @endphp
                            @if($invoiceLink)
                                <a href="{{ $invoiceLink }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 dark:text-primary-400 underline hover:no-underline">{{ $asset->invoice_number ?? '—' }}</a>
                            @else
                                {{ $asset->invoice_number ?? '—' }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">
                            @php $orderLink = $this->orderNumberLink($asset->order_number); @endphp
                            @if($orderLink)
                                <a href="{{ $orderLink }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 dark:text-primary-400 underline hover:no-underline">{{ $asset->order_number ?? '—' }}</a>
                            @else
                                {{ $asset->order_number ?? '—' }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $asset->type?->name }}</flux:table.cell>
                        <flux:table.cell class="max-w-[10rem]">
                            @php $vendorName = $asset->vendor?->name ?? '—'; @endphp
                            <flux:tooltip :content="$vendorName" position="top">
                                <span class="block truncate">{{ $vendorName }}</span>
                            </flux:tooltip>
                        </flux:table.cell>
                        <flux:table.cell>{{ $asset->owner?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @if($asset->itexia_uuid)
                                    <flux:badge color="green" size="sm">Gefunden</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm">Nicht gefunden</flux:badge>
                                @endif
                                @if($asset->is_missing)
                                    <flux:badge color="red" size="sm">Vermisst</flux:badge>
                                @endif
                                @if($asset->is_clarification)
                                    <flux:badge color="amber" size="sm">Klärung</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('apps.assets.show', $asset) }}" variant="ghost" size="sm" icon="eye" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="11" class="text-center text-zinc-500 py-8">
                            Keine Itexia-Geräte gefunden.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div>
            {{ $this->assets->links() }}
        </div>
    </div>
</x-intranet-app-assets::assets-layout>
</div>
