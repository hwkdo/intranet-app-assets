<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\BulkRecipientHandoverSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Meine Assets')] class extends Component
{
    /** @var list<int> */
    public array $selectedHandoverIds = [];

    public bool $selectPage = false;

    public string $bulkAction = 'confirm';

    public string $bulkReason = '';

    public function mount(): void
    {
        $stored = Session::get(BulkRecipientHandoverSession::MY_ASSETS_SELECTION_SESSION_KEY, []);
        $this->selectedHandoverIds = $this->sanitizeIds(is_array($stored) ? $stored : []);
        $this->pruneStaleSelection();
    }

    public function updatedSelectedHandoverIds(): void
    {
        $this->selectedHandoverIds = $this->sanitizeIds($this->selectedHandoverIds);
        Session::put(BulkRecipientHandoverSession::MY_ASSETS_SELECTION_SESSION_KEY, $this->selectedHandoverIds);
        $this->selectPage = $this->areAllCurrentPageSelected();
        $this->skipRender();
    }

    public function clearSelection(): void
    {
        $this->selectedHandoverIds = [];
        $this->selectPage = false;
        BulkRecipientHandoverSession::forgetMyAssetsBulkSelection();
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        $this->skipRender();
    }

    public function submitBulkAction(): void
    {
        $rules = [
            'selectedHandoverIds' => ['required', 'array', 'min:1'],
            'selectedHandoverIds.*' => ['integer', 'min:1'],
            'bulkAction' => ['required', 'string', 'in:confirm,reject'],
        ];

        if ($this->bulkAction === 'reject') {
            $rules['bulkReason'] = ['required', 'string', 'min:3', 'max:5000'];
        }

        $this->validate($rules);

        $ids = $this->sanitizeIds($this->selectedHandoverIds);
        $eligibleCount = Handover::query()
            ->whereIn('id', $ids)
            ->where('recipient_user_id', auth()->id())
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->count();

        if ($eligibleCount !== count($ids)) {
            session()->flash('error', 'Mindestens eine ausgewählte Übergabe ist nicht mehr offen oder gehört nicht zu Ihrem Konto.');

            return;
        }

        if ($this->bulkAction === 'confirm') {
            BulkRecipientHandoverSession::putConfirmPending((int) auth()->id(), $ids);
            $this->redirect(route('apps.assets.handover.bulk.confirm'), navigate: false);

            return;
        }

        BulkRecipientHandoverSession::putRejectPending((int) auth()->id(), $ids, trim($this->bulkReason));
        $this->redirect(route('apps.assets.handover.bulk.reject'), navigate: false);
    }

    #[Computed]
    public function assets(): \Illuminate\Database\Eloquent\Collection
    {
        return Asset::query()
            ->with(['type', 'vendor'])
            ->where('user_id', auth()->id())
            ->orderBy('model')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, Handover> keyed by asset_id */
    #[Computed]
    public function pendingHandoversByAssetId(): \Illuminate\Support\Collection
    {
        $assetIds = $this->assets->pluck('id');
        if ($assetIds->isEmpty()) {
            return collect();
        }

        return Handover::query()
            ->where('recipient_user_id', auth()->id())
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->whereIn('asset_id', $assetIds)
            ->get()
            ->keyBy('asset_id');
    }

    /**
     * Asset-IDs, für die der aktuelle Nutzer mindestens eine bereits bestätigte Übergabe hat.
     * „Klärung anfordern“ soll nur dann angeboten werden (nicht bei offener Übergabe — dort: ablehnen).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    #[Computed]
    public function assetIdsWithConfirmedRecipientHandover(): \Illuminate\Support\Collection
    {
        $assetIds = $this->assets->pluck('id');
        if ($assetIds->isEmpty()) {
            return collect();
        }

        return Handover::query()
            ->where('recipient_user_id', auth()->id())
            ->whereNotNull('confirmed_at')
            ->whereIn('asset_id', $assetIds)
            ->pluck('asset_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  array<int|string>  $ids
     * @return list<int>
     */
    private function sanitizeIds(array $ids): array
    {
        $normalized = array_map(static fn ($id): int => (int) $id, $ids);
        $normalized = array_filter($normalized, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($normalized));
    }

    /**
     * @return list<int>
     */
    private function currentPagePendingHandoverIds(): array
    {
        return $this->pendingHandoversByAssetId
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    private function areAllCurrentPageSelected(): bool
    {
        $currentIds = $this->currentPagePendingHandoverIds();
        if ($currentIds === []) {
            return false;
        }

        return count(array_diff($currentIds, $this->selectedHandoverIds)) === 0;
    }

    /**
     * Entfernt IDs, die keine offene Übergabe mehr sind (z. B. nach Bestätigung), damit Accordion und Checkboxen konsistent bleiben.
     */
    private function pruneStaleSelection(): void
    {
        if ($this->selectedHandoverIds === []) {
            return;
        }

        $userId = (int) auth()->id();
        $validIds = Handover::query()
            ->whereIn('id', $this->selectedHandoverIds)
            ->where('recipient_user_id', $userId)
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        $pruned = array_values(array_intersect($this->selectedHandoverIds, $validIds));
        if ($pruned !== $this->selectedHandoverIds) {
            $this->selectedHandoverIds = $pruned;
            Session::put(BulkRecipientHandoverSession::MY_ASSETS_SELECTION_SESSION_KEY, $this->selectedHandoverIds);
            $this->selectPage = $this->areAllCurrentPageSelected();
        }
    }
}; ?>
@php
    /** @var list<int> $pageHandoverIds */
    $pageHandoverIds = $this->currentPagePendingHandoverIds();
@endphp
<div>
    <x-intranet-app-assets::assets-layout heading="Meine Assets" subheading="Übersicht Ihrer zugewiesenen Assets">
        <div
            class="space-y-4"
            x-data="{
                selectedIds: @json($selectedHandoverIds),
                pageHandoverIds: @json($pageHandoverIds),
                syncWire() {
                    $wire.set('selectedHandoverIds', this.selectedIds.slice().sort((a, b) => a - b));
                },
                toggleRow(id, checked) {
                    id = Number(id);
                    if (checked) {
                        if (! this.selectedIds.includes(id)) {
                            this.selectedIds.push(id);
                        }
                    } else {
                        this.selectedIds = this.selectedIds.filter((i) => i !== id);
                    }
                    this.syncWire();
                },
                togglePage(checked) {
                    if (checked) {
                        this.pageHandoverIds.forEach((id) => {
                            id = Number(id);
                            if (! this.selectedIds.includes(id)) {
                                this.selectedIds.push(id);
                            }
                        });
                    } else {
                        const onPage = new Set(this.pageHandoverIds.map(Number));
                        this.selectedIds = this.selectedIds.filter((i) => ! onPage.has(Number(i)));
                    }
                    this.syncWire();
                },
                get pageFullySelected() {
                    if (this.pageHandoverIds.length === 0) {
                        return false;
                    }
                    return this.pageHandoverIds.every((id) => this.selectedIds.includes(Number(id)));
                },
            }"
            x-on:{{ \Hwkdo\IntranetAppAssets\Support\BulkSelectionUi::CLEAR_SELECTED_IDS_EVENT }}.window="selectedIds = []"
        >
            <flux:card
                class="overflow-hidden transition-all duration-200"
                x-bind:class="selectedIds.length > 0 ? 'ring-2 ring-amber-500/90 ring-offset-2 ring-offset-white border-amber-400/90 bg-amber-50/90 shadow-sm dark:ring-amber-400/80 dark:ring-offset-zinc-950 dark:border-amber-500/60 dark:bg-amber-950/35' : ''"
            >
                <flux:accordion exclusive transition>
                    <flux:accordion.item>
                        <flux:accordion.heading
                            class="cursor-pointer select-none font-medium rounded-md px-1 py-0.5 -mx-1 transition-colors"
                            x-bind:class="selectedIds.length > 0 ? 'bg-amber-100/90 text-amber-950 dark:bg-amber-900/45 dark:text-amber-50' : ''"
                        >
                            <span x-text="selectedIds.length"></span> ausgewählt
                        </flux:accordion.heading>
                        <flux:accordion.content>
                            <div class="pt-2">
                                <div x-show="selectedIds.length === 0" x-cloak class="mb-0">
                                    <flux:callout variant="subtle" icon="information-circle" class="mb-0">
                                        <flux:callout.text>
                                            Wählen Sie einen oder mehrere Datensätze über die Checkbox in der ersten Tabellenspalte aus, um anschließend eine Mehrfachaktion auf mehrere Übergaben anwenden zu können.
                                        </flux:callout.text>
                                    </flux:callout>
                                </div>
                                <div x-show="selectedIds.length > 0" x-cloak class="space-y-4">
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <flux:radio.group wire:model.live="bulkAction" label="Aktion">
                                            <flux:radio value="confirm" label="Übergaben bestätigen" />
                                            <flux:radio value="reject" label="Übergaben ablehnen" />
                                        </flux:radio.group>
                                        @if($bulkAction === 'reject')
                                            <flux:textarea wire:model="bulkReason" label="Begründung (für alle ausgewählten)" rows="3" />
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button wire:click="submitBulkAction" variant="primary" icon="check">Auf ausgewählte anwenden</flux:button>
                                        <flux:button wire:click="clearSelection" variant="ghost">Auswahl leeren</flux:button>
                                    </div>
                                </div>
                            </div>
                        </flux:accordion.content>
                    </flux:accordion.item>
                </flux:accordion>
            </flux:card>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>
                        <label class="inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                class="size-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500 dark:border-zinc-600 dark:bg-zinc-800 dark:ring-offset-zinc-900"
                                :checked="pageFullySelected"
                                x-on:change="togglePage($event.target.checked)"
                            />
                        </label>
                    </flux:table.column>
                    <flux:table.column>Modell / Name</flux:table.column>
                    <flux:table.column>Seriennummer</flux:table.column>
                    <flux:table.column>Typ</flux:table.column>
                    <flux:table.column>Hersteller</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->assets as $asset)
                        <flux:table.row wire:key="asset-{{ $asset->id }}">
                            <flux:table.cell>
                                @php $pendingHandover = $this->pendingHandoversByAssetId->get($asset->id); @endphp
                                @if($pendingHandover)
                                    <label class="inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            class="size-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500 dark:border-zinc-600 dark:bg-zinc-800 dark:ring-offset-zinc-900"
                                            :checked="selectedIds.includes({{ (int) $pendingHandover->id }})"
                                            x-on:change="toggleRow({{ (int) $pendingHandover->id }}, $event.target.checked)"
                                        />
                                    </label>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium">{{ $asset->display_name }}</div>
                                @if($asset->itexia_id)
                                    <div class="text-xs text-zinc-500">{{ $asset->itexia_id }}</div>
                                @endif
                                @if($asset->is_clarification && ! $this->pendingHandoversByAssetId->has($asset->id))
                                    <flux:badge color="amber" size="sm" class="mt-1">In Klärung</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-sm">{{ $asset->serial_number }}</flux:table.cell>
                            <flux:table.cell>{{ $asset->type?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="max-w-[10rem]">
                                @php $vendorName = $asset->vendor?->name ?? '—'; @endphp
                                <flux:tooltip :content="$vendorName" position="top">
                                    <span class="block truncate">{{ $vendorName }}</span>
                                </flux:tooltip>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap items-center gap-1">
                                    @php $pendingHandover = $this->pendingHandoversByAssetId->get($asset->id); @endphp
                                    @if($pendingHandover)
                                        <flux:tooltip content="Übergabe bestätigen — Erhalt des Assets bestätigen" position="top">
                                            <flux:button
                                                href="{{ route('apps.assets.handover.confirm', $pendingHandover) }}"
                                                variant="primary"
                                                size="sm"
                                                icon="check-circle"
                                                class="!px-2"
                                                aria-label="Übergabe bestätigen"
                                            ></flux:button>
                                        </flux:tooltip>
                                        <flux:tooltip content="Übergabe ablehnen — mit Begründung (LDAP-Passwort erforderlich)" position="top">
                                            <flux:button
                                                href="{{ route('apps.assets.handover.reject', $pendingHandover) }}"
                                                variant="danger"
                                                size="sm"
                                                icon="x-circle"
                                                class="!px-2"
                                                aria-label="Übergabe ablehnen"
                                            ></flux:button>
                                        </flux:tooltip>
                                    @endif
                                    @if(
                                        ! $asset->is_clarification
                                        && ! $pendingHandover
                                        && $this->assetIdsWithConfirmedRecipientHandover->contains((int) $asset->id)
                                    )
                                        <flux:tooltip content="Klärung anfordern — wenn der Bestand nicht stimmt (wird protokolliert)" position="top">
                                            <flux:button
                                                href="{{ route('apps.assets.clarification.request', $asset) }}"
                                                variant="ghost"
                                                size="sm"
                                                icon="exclamation-triangle"
                                                class="!px-2"
                                                aria-label="Klärung anfordern"
                                            ></flux:button>
                                        </flux:tooltip>
                                    @endif
                                    <flux:button href="{{ route('apps.assets.show', [$asset, 'from' => 'meine-assets']) }}" variant="ghost" size="sm" icon="eye">
                                        Detail
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-zinc-500 py-8">
                                Ihnen sind derzeit keine Assets zugewiesen.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
