@php
    use Hwkdo\IntranetAppAssets\Livewire\Apps\Assets\AdminHandoversOverview;
    use Hwkdo\IntranetAppAssets\Services\HandoverRejectionAdminResolutionService;
    use Hwkdo\IntranetAppAssets\Services\OpenHandoverAdminResolutionService;
    use Illuminate\Support\Str;

    $pageHandoverIds = $handovers->getCollection()->pluck('id')->map(static fn (int|string $id): int => (int) $id)->values()->all();
    $isRejected = $this->filter === AdminHandoversOverview::FILTER_REJECTED;
@endphp
<div>
    <x-intranet-app-assets::assets-layout
        heading="Übergaben"
        :subheading="$isRejected ? 'Abgelehnte Übergaben — Besitz und Standort klären' : 'Offene Übergaben — noch nicht bestätigt'"
    >
        <div class="space-y-4">
            <flux:radio.group wire:model.live="filter" label="Ansicht" variant="segmented">
                <flux:radio value="{{ AdminHandoversOverview::FILTER_OPEN }}" label="Offen" />
                <flux:radio value="{{ AdminHandoversOverview::FILTER_REJECTED }}" label="Abgelehnt" />
            </flux:radio.group>

            <div class="max-w-sm">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Suchen nach Asset, SN, Itexia-ID, Empfänger…"
                    icon="magnifying-glass"
                    clearable
                />
            </div>

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
                                                @if($isRejected)
                                                    Wählen Sie einen oder mehrere Datensätze über die Checkbox in der ersten Tabellenspalte aus, um anschließend eine Mehrfachaktion auf mehrere abgelehnte Übergaben anwenden zu können.
                                                @else
                                                    Wählen Sie einen oder mehrere Datensätze über die Checkbox in der ersten Tabellenspalte aus, um anschließend eine Mehrfachaktion auf mehrere offene Übergaben anwenden zu können.
                                                @endif
                                            </flux:callout.text>
                                        </flux:callout>
                                    </div>
                                    <div x-show="selectedIds.length > 0" x-cloak class="space-y-4">
                                        @if($isRejected)
                                            <div class="grid gap-3 md:grid-cols-2">
                                                <flux:radio.group wire:model.live="resolution" label="Aktion">
                                                    <flux:radio value="{{ HandoverRejectionAdminResolutionService::ResolutionNewOwner }}" label="Neuen Besitzer zuweisen" />
                                                    <flux:radio value="{{ HandoverRejectionAdminResolutionService::ResolutionSetLocation }}" label="Besitzer entfernen und Standort setzen" />
                                                    <flux:radio value="{{ HandoverRejectionAdminResolutionService::ResolutionMarkMissing }}" label="Als vermisst markieren" />
                                                </flux:radio.group>
                                                <flux:textarea wire:model="bulkReason" label="Grund / Notiz (für alle ausgewählten)" rows="3" />
                                            </div>
                                            @if($resolution === HandoverRejectionAdminResolutionService::ResolutionNewOwner)
                                                <flux:select wire:model="newOwnerUserId" variant="listbox" searchable label="Neuer Besitzer" placeholder="Benutzer wählen…">
                                                    @foreach($users as $user)
                                                        <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                                                    @endforeach
                                                </flux:select>
                                            @endif
                                            @if($resolution === HandoverRejectionAdminResolutionService::ResolutionSetLocation)
                                                <flux:input wire:model="location" label="Standort" placeholder="z. B. Lager IT, Raum …" />
                                            @endif
                                        @else
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
                                                    @foreach($users as $user)
                                                        <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                                                    @endforeach
                                                </flux:select>
                                            @endif
                                            @if($resolution === OpenHandoverAdminResolutionService::ResolutionSetLocation)
                                                <flux:input wire:model="location" label="Standort" placeholder="z. B. Lager IT, Raum …" />
                                            @endif
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
                        @if($isRejected)
                            <flux:table.column>Abgelehnt am</flux:table.column>
                        @else
                            <flux:table.column>Erstellt am</flux:table.column>
                        @endif
                        <flux:table.column>Asset</flux:table.column>
                        <flux:table.column>Empfänger</flux:table.column>
                        @if($isRejected)
                            <flux:table.column>Begründung (Auszug)</flux:table.column>
                        @else
                            <flux:table.column>Ausgestellt von</flux:table.column>
                        @endif
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($handovers as $handover)
                            @php
                                $reasonNote = $isRejected ? $handover->notes->first() : null;
                                $reasonPreview = $reasonNote ? Str::limit(strip_tags($reasonNote->note), 120) : '—';
                            @endphp
                            <flux:table.row wire:key="ho-{{ $this->filter }}-{{ $handover->id }}">
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
                                <flux:table.cell>
                                    @if($isRejected)
                                        {{ $handover->rejected_at?->format('d.m.Y H:i') ?? '—' }}
                                    @else
                                        {{ $handover->created_at?->format('d.m.Y H:i') ?? '—' }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($handover->asset)
                                        <div class="font-medium">{{ $handover->asset->display_name }}</div>
                                        <div class="font-mono text-xs text-zinc-500">{{ $handover->asset->serial_number }}</div>
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $handover->recipient?->name ?? '—' }}</flux:table.cell>
                                @if($isRejected)
                                    <flux:table.cell class="max-w-xs text-sm text-zinc-600 dark:text-zinc-300">{{ $reasonPreview }}</flux:table.cell>
                                @else
                                    <flux:table.cell>{{ $handover->issuer?->name ?? '—' }}</flux:table.cell>
                                @endif
                                <flux:table.cell>
                                    @if($isRejected)
                                        <flux:button
                                            :href="route('apps.assets.admin.rejected-handover.resolve', $handover)"
                                            variant="primary"
                                            size="sm"
                                            icon="wrench"
                                        >
                                            Bearbeiten
                                        </flux:button>
                                    @else
                                        <flux:button
                                            :href="route('apps.assets.admin.open-handover.resolve', $handover)"
                                            variant="primary"
                                            size="sm"
                                            icon="wrench"
                                        >
                                            Auflösen
                                        </flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">
                                    @if($isRejected)
                                        Keine abgelehnten Übergaben.
                                    @else
                                        Keine offenen Übergaben.
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>

                <div>
                    {{ $handovers->links() }}
                </div>
            </div>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
