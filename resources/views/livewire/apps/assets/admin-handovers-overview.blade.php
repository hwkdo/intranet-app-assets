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
        :heading="$isRejected ? 'Abgelehnte Übergaben' : 'Unbestätigte Übergaben'"
        :subheading="$isRejected ? 'Besitz und Standort klären' : 'Noch nicht vom Empfänger bestätigt'"
    >
        <div class="space-y-4">
            <div class="glass-card flex flex-col gap-4 p-4 lg:flex-row lg:items-end lg:justify-between" style="--glass-opacity: 0.92; overflow: visible">
                <div class="shrink-0">
                    <flux:radio.group wire:model.live="filter" label="Ansicht" variant="segmented">
                        <flux:radio value="{{ AdminHandoversOverview::FILTER_OPEN }}" label="Unbestätigt" />
                        <flux:radio value="{{ AdminHandoversOverview::FILTER_REJECTED }}" label="Abgelehnt" />
                    </flux:radio.group>
                </div>

                <div class="w-full lg:w-80">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suchen nach Asset, SN, Itexia-ID, Empfänger…"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>
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
                <section
                    class="glass-card overflow-hidden p-4 transition-all duration-200"
                    x-bind:class="selectedIds.length > 0 ? 'ring-2 ring-amber-500/90 ring-offset-2 ring-offset-white border-amber-400/90 bg-[#d0e3f9]/80 shadow-sm dark:ring-amber-400/80 dark:ring-offset-[#04214e] dark:border-amber-500/60 dark:bg-amber-950/35' : ''"
                >
                    <flux:accordion exclusive transition>
                        <flux:accordion.item>
                            <flux:accordion.heading
                                class="cursor-pointer select-none rounded-lg px-2 py-2.5 font-medium transition-colors"
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
                                                    Wählen Sie einen oder mehrere Datensätze über die Checkbox in der ersten Tabellenspalte aus, um anschließend eine Mehrfachaktion auf mehrere unbestätigte Übergaben anwenden zu können.
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
                                                <x-intranet-app-assets::unowned-device-type-select
                                                    class="mt-3"
                                                    wire-model="deviceType"
                                                    error-name="deviceType"
                                                />
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
                                                <x-intranet-app-assets::unowned-device-type-select
                                                    class="mt-3"
                                                    wire-model="deviceType"
                                                    error-name="deviceType"
                                                />
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
                </section>

                <div class="glass-card overflow-hidden">
<flux:table>
                    <flux:table.columns>
                        <flux:table.column>
                            <label class="inline-flex cursor-pointer items-center">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-[#d0e3f9] text-amber-600 focus:ring-amber-500 dark:border-white/10 dark:bg-white/5 dark:ring-offset-[#04214e]"
                                    :checked="pageFullySelected"
                                    x-on:change="togglePage($event.target.checked)"
                                />
                            </label>
                        </flux:table.column>
                        @if($isRejected)
                            @include('intranet-app-assets::partials.sortable-column', ['column' => 'rejected_at', 'label' => 'Abgelehnt am'])
                        @else
                            @include('intranet-app-assets::partials.sortable-column', ['column' => 'created_at', 'label' => 'Erstellt am'])
                        @endif
                        @include('intranet-app-assets::partials.sortable-column', ['column' => 'asset', 'label' => 'Asset'])
                        @include('intranet-app-assets::partials.sortable-column', ['column' => 'recipient', 'label' => 'Empfänger'])
                        @if($isRejected)
                            <flux:table.column>Begründung (Auszug)</flux:table.column>
                        @else
                            @include('intranet-app-assets::partials.sortable-column', ['column' => 'issuer', 'label' => 'Ausgestellt von'])
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
                                            class="size-4 rounded border-[#d0e3f9] text-amber-600 focus:ring-amber-500 dark:border-white/10 dark:bg-white/5 dark:ring-offset-[#04214e]"
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
                                        <div class="font-mono text-xs text-slate-600 dark:text-white/70">{{ $handover->asset->serial_number }}</div>
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $handover->recipient?->name ?? '—' }}</flux:table.cell>
                                @if($isRejected)
                                    <flux:table.cell class="max-w-xs text-sm text-slate-600 dark:text-white/70">{{ $reasonPreview }}</flux:table.cell>
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
                                <flux:table.cell colspan="6" class="px-3 py-3">
<x-intranet-app-assets::hwk-empty bare icon="inbox">
@if($isRejected)
                                        Keine abgelehnten Übergaben.
                                    @else
                                        Keine unbestätigten Übergaben.
                                    @endif
</x-intranet-app-assets::hwk-empty>
</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
</div>

                <div>
                    <div class="glass-card px-4 py-3" style="--glass-opacity: 0.92">
                        {{ $handovers->links() }}
                    </div>
                </div>
            </div>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
