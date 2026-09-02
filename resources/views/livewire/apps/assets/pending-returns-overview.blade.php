@php
    $pageReturnIds = $returns->getCollection()->pluck('id')->map(static fn (int|string $id): int => (int) $id)->values()->all();
@endphp
<div>
    <x-intranet-app-assets::assets-layout
        heading="Offene Rückgaben"
        subheading="Warten auf Empfangsbestätigung und Zuordnung"
    >
        <div
            class="space-y-4"
            x-data="{
                selectedIds: @json($selectedReturnIds),
                pageIds: @json($pageReturnIds),
                syncWire() {
                    $wire.set('selectedReturnIds', this.selectedIds.slice().sort((a, b) => a - b));
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
                                            Wählen Sie einen oder mehrere Datensätze über die Checkbox in der ersten Tabellenspalte aus, um anschließend eine Mehrfachaktion auf mehrere Rückgaben anwenden zu können.
                                        </flux:callout.text>
                                    </flux:callout>
                                </div>
                                <div x-show="selectedIds.length > 0" x-cloak class="space-y-4">
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <flux:radio.group wire:model.live="resolution" label="Aktion">
                                            <flux:radio value="{{ \Hwkdo\IntranetAppAssets\Services\AssetReturnAdminCompletionService::ResolutionNewOwner }}" label="Neuen Besitzer zuweisen" />
                                            <flux:radio value="{{ \Hwkdo\IntranetAppAssets\Services\AssetReturnAdminCompletionService::ResolutionSetLocation }}" label="Besitzer entfernen und Standort setzen" />
                                        </flux:radio.group>

                                        <flux:textarea wire:model="bulkReason" label="Grund / Notiz (für alle ausgewählten)" rows="3" />
                                    </div>

                                    @if($resolution === \Hwkdo\IntranetAppAssets\Services\AssetReturnAdminCompletionService::ResolutionNewOwner)
                                        <flux:select
                                            wire:model="newOwnerUserId"
                                            variant="listbox"
                                            searchable
                                            label="Neuer Besitzer"
                                            placeholder="Benutzer wählen…"
                                        >
                                            @foreach($users as $user)
                                                <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @endif

                                    @if($resolution === \Hwkdo\IntranetAppAssets\Services\AssetReturnAdminCompletionService::ResolutionSetLocation)
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
                    <flux:table.column>Asset</flux:table.column>
                    <flux:table.column>Zurückgegeben von</flux:table.column>
                    <flux:table.column>Termin</flux:table.column>
                    <flux:table.column>Eingeleitet</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($returns as $return)
                        @php $h = $return->handover; $a = $h?->asset; @endphp
                        <flux:table.row wire:key="pending-return-{{ $return->id }}">
                            <flux:table.cell>
                                <label class="inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500 dark:border-zinc-600 dark:bg-zinc-800 dark:ring-offset-zinc-900"
                                        :checked="selectedIds.includes({{ (int) $return->id }})"
                                        x-on:change="toggleRow({{ (int) $return->id }}, $event.target.checked)"
                                    />
                                </label>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($a)
                                    <div class="font-medium">{{ $a->display_name }}</div>
                                    <div class="font-mono text-xs text-zinc-500">{{ $a->serial_number }}</div>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $return->initiatedBy?->name ?? $h?->recipient?->name ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-600 dark:text-zinc-300">
                                @if($return->isScheduled())
                                    <div>{{ \Hwkdo\IntranetAppAssets\Support\AssetReturnSchedulePresenter::formattedScheduledAt($return->scheduled_at) }}</div>
                                    @php $badge = \Hwkdo\IntranetAppAssets\Support\AssetReturnSchedulePresenter::scheduleBadge($return); @endphp
                                    @if($badge)
                                        <flux:badge size="sm" :color="$badge['color']">{{ $badge['label'] }}</flux:badge>
                                    @endif
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $return->created_at?->format('d.m.Y H:i') ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button
                                    :href="route('apps.assets.admin.return.complete', $return)"
                                    variant="primary"
                                    size="sm"
                                    icon="shield-check"
                                >
                                    Bearbeiten
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">
                                Keine offenen Rückgaben.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <div>
                {{ $returns->links() }}
            </div>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
