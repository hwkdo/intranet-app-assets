<?php

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Enums\D3InvoiceAnalysisStatus;
use Hwkdo\IntranetAppAssets\Jobs\AnalyzeD3InvoiceJob;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\D3InvoiceAnalysis;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceValidationService;
use Hwkdo\IntranetAppAssets\Support\DmsLinkHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorConcrete;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Rechnungen')] class extends Component
{
    use WithPagination;

    /** analyzed | pending */
    #[Url]
    public string $tab = 'analyzed';

    public string $search = '';

    public bool $onlyFailed = false;

    public bool $showDetailModal = false;

    public ?int $detailAnalysisId = null;

    public bool $showErrorModal = false;

    public ?int $errorAnalysisId = null;

    public function mount(): void
    {
        $this->authorize('manage-app-assets');
    }

    public function updatingSearch(): void
    {
        $this->resetPage('analysisPage');
        $this->resetPage('pendingPage');
    }

    public function updatedTab(): void
    {
        $this->resetPage('analysisPage');
        $this->resetPage('pendingPage');
    }

    public function updatedOnlyFailed(): void
    {
        $this->resetPage('analysisPage');
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

    public function invoiceUrl(?string $number): ?string
    {
        return DmsLinkHelper::invoiceUrl($this->dmsBaseUrl, $number);
    }

    public function openDetail(int $analysisId): void
    {
        $this->authorize('manage-app-assets');
        $this->detailAnalysisId = $analysisId;
        $this->showDetailModal = true;
    }

    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->detailAnalysisId = null;
    }

    public function updatedShowDetailModal(bool $value): void
    {
        if (! $value) {
            $this->detailAnalysisId = null;
        }
    }

    public function openError(int $analysisId): void
    {
        $this->authorize('manage-app-assets');
        $this->errorAnalysisId = $analysisId;
        $this->showErrorModal = true;
    }

    public function closeError(): void
    {
        $this->showErrorModal = false;
        $this->errorAnalysisId = null;
    }

    public function updatedShowErrorModal(bool $value): void
    {
        if (! $value) {
            $this->errorAnalysisId = null;
        }
    }

    public function startAnalysis(string $documentId, bool $force = false): void
    {
        $this->authorize('manage-app-assets');
        $documentId = D3InvoiceAnalysis::normalizeDocumentId($documentId);
        if (! D3InvoiceValidationService::isValidFormat($documentId)) {
            Flux::toast('Ungültige D3-Dokument-ID.', variant: 'danger');

            return;
        }

        D3InvoiceAnalysis::requestAnalysis($documentId, $force);
        AnalyzeD3InvoiceJob::dispatch($documentId, $force);

        Flux::toast(
            $force
                ? 'Analyse für '.$documentId.' wird neu gestartet.'
                : 'Analyse für '.$documentId.' wurde in die Warteschlange gestellt.',
            variant: 'success',
        );

        unset($this->pendingDocuments);
    }

    /**
     * @return LengthAwarePaginator<int, D3InvoiceAnalysis>
     */
    public function getAnalysesPaginatorProperty(): LengthAwarePaginator
    {
        $q = trim($this->search);

        return D3InvoiceAnalysis::query()
            ->withCount('assets')
            ->when($q !== '', function ($query) use ($q): void {
                $query->where('d3_document_id', 'like', '%'.$q.'%');
            })
            ->when($this->onlyFailed, function ($query): void {
                $query->where('status', D3InvoiceAnalysisStatus::Failed);
            })
            ->orderByDesc('updated_at')
            ->paginate(20, ['*'], 'analysisPage');
    }

    /**
     * @return Collection<int, object{d3_document_id: string, row: ?D3InvoiceAnalysis, asset_count: int}>
     */
    #[Computed]
    public function pendingDocuments(): Collection
    {
        $needle = trim($this->search);
        $ids = Asset::query()
            ->whereNotNull('invoice_number')
            ->where('invoice_number', 'like', 'T%')
            ->pluck('invoice_number')
            ->map(fn (mixed $v): string => trim((string) $v))
            ->unique()
            ->filter(fn (string $id): bool => D3InvoiceValidationService::isValidFormat($id))
            ->values();

        $counts = Asset::query()
            ->whereNotNull('invoice_number')
            ->whereIn('invoice_number', $ids)
            ->selectRaw('invoice_number, count(*) as c')
            ->groupBy('invoice_number')
            ->pluck('c', 'invoice_number');

        $rows = D3InvoiceAnalysis::query()
            ->whereIn('d3_document_id', $ids)
            ->get()
            ->keyBy(fn (D3InvoiceAnalysis $a): string => $a->d3_document_id);

        $pending = $ids
            ->filter(function (string $id) use ($rows): bool {
                return D3InvoiceAnalysis::findCompletedPayloadForDocument($id) === null;
            })
            ->when($needle !== '', fn (Collection $c): Collection => $c->filter(fn (string $id): bool => str_contains(strtolower($id), strtolower($needle))))
            ->map(function (string $id) use ($rows, $counts): object {
                return (object) [
                    'd3_document_id' => $id,
                    'row' => $rows->get($id),
                    'asset_count' => (int) ($counts[$id] ?? 0),
                ];
            })
            ->values();

        return $pending;
    }

    /**
     * @return LengthAwarePaginator<int, object{d3_document_id: string, row: ?D3InvoiceAnalysis, asset_count: int}>
     */
    public function pendingPaginator(): LengthAwarePaginator
    {
        $all = $this->pendingDocuments;
        $perPage = 20;
        $page = max(1, (int) Paginator::resolveCurrentPage('pendingPage'));
        $total = $all->count();
        $slice = $all->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginatorConcrete(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'pendingPage',
            ]
        );
    }

    #[Computed]
    public function detailAnalysis(): ?D3InvoiceAnalysis
    {
        if ($this->detailAnalysisId === null) {
            return null;
        }

        return D3InvoiceAnalysis::query()->find($this->detailAnalysisId);
    }

    #[Computed]
    public function errorAnalysis(): ?D3InvoiceAnalysis
    {
        if ($this->errorAnalysisId === null) {
            return null;
        }

        return D3InvoiceAnalysis::query()->find($this->errorAnalysisId);
    }

    public function statusLabel(D3InvoiceAnalysis $row): string
    {
        return match ($row->status) {
            D3InvoiceAnalysisStatus::Completed => 'Abgeschlossen',
            D3InvoiceAnalysisStatus::Pending => 'Ausstehend',
            D3InvoiceAnalysisStatus::Failed => 'Fehlgeschlagen',
        };
    }

    public function statusVariant(D3InvoiceAnalysis $row): string
    {
        return match ($row->status) {
            D3InvoiceAnalysisStatus::Completed => 'success',
            D3InvoiceAnalysisStatus::Pending => 'warning',
            D3InvoiceAnalysisStatus::Failed => 'danger',
        };
    }
};
?>

<div>
    <x-intranet-app-assets::assets-layout
        heading="Rechnungen"
        subheading="D3-Rechnungsanalysen (Vision-Cache): Ergebnisse einsehen und fehlende Analysen anstoßen"
        :render-app-index-auto="false"
    >
        <div class="space-y-6">
            <flux:field>
                <flux:label>Suche</flux:label>
                <flux:input wire:model.live.debounce.400ms="search" placeholder="T-Nummer …" class="max-w-md" />
            </flux:field>

            <flux:button.group>
                <flux:button wire:click="$set('tab', 'analyzed')" :variant="$tab === 'analyzed' ? 'primary' : 'ghost'">
                    Gespeicherte Analysen
                </flux:button>
                <flux:button wire:click="$set('tab', 'pending')" :variant="$tab === 'pending' ? 'primary' : 'ghost'">
                    Analyse offen
                </flux:button>
            </flux:button.group>

            @if($tab === 'analyzed')
                <div class="flex items-center gap-3">
                    <flux:checkbox wire:model.live="onlyFailed" label="Nur fehlgeschlagene anzeigen" />
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>D3-ID</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Assets</flux:table.column>
                        <flux:table.column>Modell</flux:table.column>
                        <flux:table.column>Analysiert</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($this->analysesPaginator as $row)
                            <flux:table.row wire:key="analysis-{{ $row->id }}">
                                <flux:table.cell class="font-mono text-sm">
                                    @if($this->invoiceUrl($row->d3_document_id))
                                        <flux:link href="{{ $this->invoiceUrl($row->d3_document_id) }}" target="_blank" class="font-mono">
                                            {{ $row->d3_document_id }}
                                        </flux:link>
                                    @else
                                        {{ $row->d3_document_id }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :variant="$this->statusVariant($row)">{{ $this->statusLabel($row) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $row->assets_count }}</flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ $row->vision_model ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="text-sm">
                                    {{ $row->analyzed_at?->format('d.m.Y H:i') ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="flex flex-wrap gap-1 justify-end">
                                    @if($row->status === \Hwkdo\IntranetAppAssets\Enums\D3InvoiceAnalysisStatus::Completed && is_array($row->result_json))
                                        <flux:button wire:click="openDetail({{ $row->id }})" size="sm" variant="ghost" icon="eye">
                                            Ergebnis
                                        </flux:button>
                                    @endif
                                    @if($row->status === \Hwkdo\IntranetAppAssets\Enums\D3InvoiceAnalysisStatus::Failed)
                                        <flux:button wire:click="openError({{ $row->id }})" size="sm" variant="ghost" icon="exclamation-triangle">
                                            Fehlerdetails
                                        </flux:button>
                                    @endif
                                    <flux:button
                                        wire:click="startAnalysis('{{ $row->d3_document_id }}', true)"
                                        wire:confirm="Analyse erneut in die Warteschlange stellen?"
                                        size="sm"
                                        variant="ghost"
                                        icon="arrow-path"
                                    >
                                        Neu starten
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">
                                    Keine Einträge im Cache.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>

                <div>
                    {{ $this->analysesPaginator->links() }}
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>D3-ID</flux:table.column>
                        <flux:table.column>Queue-Status</flux:table.column>
                        <flux:table.column>Assets</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($this->pendingPaginator() as $item)
                            <flux:table.row wire:key="pending-{{ $item->d3_document_id }}">
                                <flux:table.cell class="font-mono text-sm">
                                    @if($this->invoiceUrl($item->d3_document_id))
                                        <flux:link href="{{ $this->invoiceUrl($item->d3_document_id) }}" target="_blank" class="font-mono">
                                            {{ $item->d3_document_id }}
                                        </flux:link>
                                    @else
                                        {{ $item->d3_document_id }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($item->row)
                                        <flux:badge :variant="$this->statusVariant($item->row)">{{ $this->statusLabel($item->row) }}</flux:badge>
                                        @if($item->row->status === \Hwkdo\IntranetAppAssets\Enums\D3InvoiceAnalysisStatus::Failed && $item->row->error_message)
                                            <div class="mt-1 max-w-md text-xs text-red-600 dark:text-red-400">{{ \Illuminate\Support\Str::limit($item->row->error_message, 120) }}</div>
                                        @endif
                                    @else
                                        <flux:badge variant="zinc">Noch nicht gestartet</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $item->asset_count }}</flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <flux:button
                                        wire:click="startAnalysis('{{ $item->d3_document_id }}')"
                                        size="sm"
                                        variant="primary"
                                        icon="play"
                                    >
                                        Analyse starten
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">
                                    Keine offenen T-Nummern (alle hinterlegten Rechnungen haben eine gültige abgeschlossene Analyse).
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>

                <div>
                    {{ $this->pendingPaginator()->links() }}
                </div>
            @endif
        </div>
    </x-intranet-app-assets::assets-layout>

    <flux:modal wire:model="showDetailModal" class="max-w-4xl" variant="flyout">
        @if($this->detailAnalysis)
            @php($da = $this->detailAnalysis)
            <div class="space-y-4">
                <flux:heading size="lg">Analyse {{ $da->d3_document_id }}</flux:heading>
                <flux:text class="text-zinc-500">
                    Modell: {{ $da->vision_model ?? '—' }} · Stand: {{ $da->analyzed_at?->format('d.m.Y H:i') ?? '—' }}
                </flux:text>
                <div class="max-h-[70vh] overflow-auto rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <pre class="whitespace-pre-wrap break-words text-xs font-mono text-zinc-800 dark:text-zinc-200">{{ json_encode($da->result_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="closeDetail" variant="ghost">Schließen</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="showErrorModal" class="max-w-3xl" variant="flyout">
        @if($this->errorAnalysis)
            @php($ea = $this->errorAnalysis)
            <div class="space-y-4">
                <flux:heading size="lg">Fehler {{ $ea->d3_document_id }}</flux:heading>
                <flux:text class="text-zinc-500">
                    Status: {{ $this->statusLabel($ea) }} · Zeit: {{ $ea->failed_at?->format('d.m.Y H:i') ?? '—' }}
                </flux:text>
                <div class="max-h-[60vh] overflow-auto rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-950/20">
                    <pre class="whitespace-pre-wrap break-words text-xs font-mono text-red-800 dark:text-red-200">{{ $ea->error_message ?? 'Keine Fehlermeldung gespeichert.' }}</pre>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="closeError" variant="ghost">Schließen</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
