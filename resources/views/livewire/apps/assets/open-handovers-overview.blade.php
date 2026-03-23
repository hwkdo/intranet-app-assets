<?php

use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\OpenHandoverAdminResolutionService;
use Hwkdo\IntranetAppAssets\Support\BulkAdminWorkflowSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Offene Übergaben')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var list<int> */
    public array $selectedHandoverIds = [];

    public bool $selectPage = false;

    public string $resolution = OpenHandoverAdminResolutionService::ResolutionNewOwner;

    public ?string $newOwnerUserId = null;

    public string $location = '';

    public string $bulkReason = '';

    private const SELECTION_SESSION_KEY = 'intranet_app_assets.bulk.open_handovers.selection';

    public function mount(): void
    {
        $stored = Session::get(self::SELECTION_SESSION_KEY, []);
        $this->selectedHandoverIds = $this->sanitizeIds(is_array($stored) ? $stored : []);
        $this->pruneStaleSelection();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedHandoverIds(): void
    {
        $this->selectedHandoverIds = $this->sanitizeIds($this->selectedHandoverIds);
        Session::put(self::SELECTION_SESSION_KEY, $this->selectedHandoverIds);
        $this->selectPage = $this->areAllCurrentPageSelected();
        $this->skipRender();
    }

    public function clearSelection(): void
    {
        $this->selectedHandoverIds = [];
        $this->selectPage = false;
        Session::forget(self::SELECTION_SESSION_KEY);
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        $this->skipRender();
    }

    public function submitBulkResolve(): void
    {
        $this->authorize('manage-app-assets');

        $rules = [
            'selectedHandoverIds' => ['required', 'array', 'min:1'],
            'selectedHandoverIds.*' => ['integer', 'min:1'],
            'resolution' => ['required', 'string', 'in:'.OpenHandoverAdminResolutionService::ResolutionNewOwner.','.OpenHandoverAdminResolutionService::ResolutionSetLocation.','.OpenHandoverAdminResolutionService::ResolutionMarkMissing],
            'bulkReason' => ['required', 'string', 'min:3', 'max:5000'],
        ];

        if ($this->resolution === OpenHandoverAdminResolutionService::ResolutionNewOwner) {
            $rules['newOwnerUserId'] = ['required', 'exists:users,id'];
        }
        if ($this->resolution === OpenHandoverAdminResolutionService::ResolutionSetLocation) {
            $rules['location'] = ['required', 'string', 'min:1', 'max:255'];
        }

        $this->validate($rules);

        BulkAdminWorkflowSession::put(
            BulkAdminWorkflowSession::FLOW_OPEN_HANDOVER,
            $this->selectedHandoverIds,
            [
                'resolution' => $this->resolution,
                'new_owner_user_id' => $this->resolution === OpenHandoverAdminResolutionService::ResolutionNewOwner ? (int) $this->newOwnerUserId : null,
                'location' => $this->resolution === OpenHandoverAdminResolutionService::ResolutionSetLocation ? trim($this->location) : null,
                'bulk_reason' => trim($this->bulkReason),
            ],
        );

        $this->redirect(route('apps.assets.admin.bulk.review'), navigate: true);
    }

    #[Computed]
    public function users(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\User::query()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();
    }

    #[Computed]
    public function handovers(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return Handover::query()
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->with([
                'asset.type',
                'asset.vendor',
                'recipient',
                'issuer',
            ])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term): void {
                    $q->whereHas('asset', function ($aq) use ($term): void {
                        $aq->where('serial_number', 'like', $term)
                            ->orWhere('model', 'like', $term)
                            ->orWhere('name', 'like', $term)
                            ->orWhere('itexia_id', 'like', $term);
                    })
                        ->orWhereHas('recipient', fn ($rq) => $rq->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term))
                        ->orWhereHas('issuer', fn ($iq) => $iq->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25);
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
    private function currentPageHandoverIds(): array
    {
        return $this->handovers->getCollection()
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    private function areAllCurrentPageSelected(): bool
    {
        $currentIds = $this->currentPageHandoverIds();
        if ($currentIds === []) {
            return false;
        }

        return count(array_diff($currentIds, $this->selectedHandoverIds)) === 0;
    }

    private function pruneStaleSelection(): void
    {
        if ($this->selectedHandoverIds === []) {
            return;
        }

        $valid = Handover::query()
            ->whereIn('id', $this->selectedHandoverIds)
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        $pruned = array_values(array_intersect($this->selectedHandoverIds, $valid));
        if ($pruned !== $this->selectedHandoverIds) {
            $this->selectedHandoverIds = $pruned;
            Session::put(self::SELECTION_SESSION_KEY, $this->selectedHandoverIds);
            $this->selectPage = $this->areAllCurrentPageSelected();
        }
    }
}; ?>
<div>
    <x-intranet-app-assets::assets-layout
        heading="Offene Übergaben"
        subheading="Noch nicht bestätigte oder abgelehnte Übergaben"
    >
        <div class="space-y-4">
            <div class="max-w-sm">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Suchen nach Asset, SN, Itexia-ID, Empfänger…"
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            @php
                $pageHandoverIds = $this->handovers->getCollection()->pluck('id')->map(static fn (int|string $id): int => (int) $id)->values()->all();
            @endphp
            <div
                class="space-y-4"
                x-data="{
                    selectedIds: @json($selectedHandoverIds),
                    pageIds: @json($pageHandoverIds),
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
                            this.pageIds.forEach((id) => {
                                id = Number(id);
                                if (! this.selectedIds.includes(id)) {
                                    this.selectedIds.push(id);
                                }
                            });
                        } else {
                            const onPage = new Set(this.pageIds.map(Number));
                            this.selectedIds = this.selectedIds.filter((i) => ! onPage.has(Number(i)));
                        }
                        this.syncWire();
                    },
                    get pageFullySelected() {
                        if (this.pageIds.length === 0) {
                            return false;
                        }
                        return this.pageIds.every((id) => this.selectedIds.includes(Number(id)));
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
                                            Wählen Sie einen oder mehrere Datensätze über die Checkbox in der ersten Tabellenspalte aus, um anschließend eine Mehrfachaktion auf mehrere offene Übergaben anwenden zu können.
                                        </flux:callout.text>
                                    </flux:callout>
                                </div>
                                <div x-show="selectedIds.length > 0" x-cloak class="space-y-4">
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <flux:radio.group wire:model.live="resolution" label="Aktion">
                                            <flux:radio value="{{ OpenHandoverAdminResolutionService::ResolutionNewOwner }}" label="Neuen Besitzer zuweisen" />
                                            <flux:radio value="{{ OpenHandoverAdminResolutionService::ResolutionSetLocation }}" label="Besitzer entfernen und Standort setzen" />
                                            <flux:radio value="{{ OpenHandoverAdminResolutionService::ResolutionMarkMissing }}" label="Als vermisst markieren" />
                                        </flux:radio.group>
                                        <flux:textarea wire:model="bulkReason" label="Grund / Notiz (für alle ausgewählten)" rows="3" />
                                    </div>

                                    @if($resolution === OpenHandoverAdminResolutionService::ResolutionNewOwner)
                                        <flux:select wire:model="newOwnerUserId" variant="listbox" searchable label="Neuer Besitzer" placeholder="Benutzer wählen…">
                                            @foreach($this->users as $user)
                                                <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @endif

                                    @if($resolution === OpenHandoverAdminResolutionService::ResolutionSetLocation)
                                        <flux:input wire:model="location" label="Standort" placeholder="z. B. Lager IT, Raum …" />
                                    @endif

                                    <div class="flex flex-wrap gap-2">
                                        <flux:button wire:click="submitBulkResolve" variant="primary" icon="check">Auf ausgewählte anwenden</flux:button>
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
                    <flux:table.column>Erstellt am</flux:table.column>
                    <flux:table.column>Asset</flux:table.column>
                    <flux:table.column>Empfänger</flux:table.column>
                    <flux:table.column>Ausgestellt von</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->handovers as $handover)
                        <flux:table.row wire:key="open-ho-{{ $handover->id }}">
                            <flux:table.cell>
                                <label class="inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500 dark:border-zinc-600 dark:bg-zinc-800 dark:ring-offset-zinc-900"
                                        :checked="selectedIds.includes({{ (int) $handover->id }})"
                                        x-on:change="toggleRow({{ (int) $handover->id }}, $event.target.checked)"
                                    />
                                </label>
                            </flux:table.cell>
                            <flux:table.cell>{{ $handover->created_at?->format('d.m.Y H:i') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if($handover->asset)
                                    <div class="font-medium">{{ $handover->asset->display_name }}</div>
                                    <div class="font-mono text-xs text-zinc-500">{{ $handover->asset->serial_number }}</div>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $handover->recipient?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $handover->issuer?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button
                                    :href="route('apps.assets.admin.open-handover.resolve', $handover)"
                                    variant="primary"
                                    size="sm"
                                    icon="wrench"
                                >
                                    Auflösen
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">
                                Keine offenen Übergaben.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <div>
                {{ $this->handovers->links() }}
            </div>
            </div>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
