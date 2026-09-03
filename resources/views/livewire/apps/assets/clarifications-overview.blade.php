@php
    $pageAssetIds = $assets->getCollection()->pluck('id')->map(static fn (int|string $id): int => (int) $id)->values()->all();
@endphp
<div>
    <x-intranet-app-assets::assets-layout
        heading="Assets in Klärung"
        subheading="Vom Besitzer gemeldete Klärungsfälle"
    >
        <div
            class="space-y-4"
            x-data="{
                selectedIds: @json($selectedAssetIds),
                pageIds: @json($pageAssetIds),
                syncWire() {
                    $wire.set('selectedAssetIds', this.selectedIds.slice().sort((a, b) => a - b));
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
                                            Wählen Sie einen oder mehrere Datensätze über die Checkbox in der ersten Tabellenspalte aus, um anschließend eine Mehrfachaktion auf mehrere Klärungsfälle anwenden zu können.
                                        </flux:callout.text>
                                    </flux:callout>
                                </div>
                                <div x-show="selectedIds.length > 0" x-cloak class="space-y-4">
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <flux:radio.group wire:model.live="resolution" label="Aktion">
                                            <flux:radio value="{{ \Hwkdo\IntranetAppAssets\Services\AssetClarificationAdminResolutionService::ResolutionClearOnly }}" label="Nur Klärungs-Flag entfernen" />
                                            <flux:radio value="{{ \Hwkdo\IntranetAppAssets\Services\AssetClarificationAdminResolutionService::ResolutionNewOwner }}" label="Neuen Besitzer zuweisen" />
                                            <flux:radio value="{{ \Hwkdo\IntranetAppAssets\Services\AssetClarificationAdminResolutionService::ResolutionSetLocation }}" label="Besitzer entfernen und Standort setzen" />
                                            <flux:radio value="{{ \Hwkdo\IntranetAppAssets\Services\AssetClarificationAdminResolutionService::ResolutionMarkMissing }}" label="Als vermisst markieren" />
                                        </flux:radio.group>
                                        <flux:textarea wire:model="bulkReason" label="Grund / Notiz (für alle ausgewählten)" rows="3" />
                                    </div>

                                    @if($resolution === \Hwkdo\IntranetAppAssets\Services\AssetClarificationAdminResolutionService::ResolutionNewOwner)
                                        <flux:select wire:model="newOwnerUserId" variant="listbox" searchable label="Neuer Besitzer" placeholder="Benutzer wählen…">
                                            @foreach($users as $user)
                                                <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @endif

                                    @if($resolution === \Hwkdo\IntranetAppAssets\Services\AssetClarificationAdminResolutionService::ResolutionSetLocation)
                                        <flux:input wire:model="location" label="Standort" placeholder="z. B. Lager IT, Raum …" />
                                        <x-intranet-app-assets::unowned-device-type-select
                                            class="mt-3"
                                            wire-model="deviceType"
                                            error-name="deviceType"
                                        />
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
                    <flux:table.column>Seriennummer</flux:table.column>
                    <flux:table.column>Besitzer (laut System)</flux:table.column>
                    <flux:table.column>Standort</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($assets as $asset)
                        <flux:table.row wire:key="clarification-asset-{{ $asset->id }}">
                            <flux:table.cell>
                                <label class="inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500 dark:border-zinc-600 dark:bg-zinc-800 dark:ring-offset-zinc-900"
                                        :checked="selectedIds.includes({{ (int) $asset->id }})"
                                        x-on:change="toggleRow({{ (int) $asset->id }}, $event.target.checked)"
                                    />
                                </label>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium">{{ $asset->display_name }}</div>
                                @if($asset->itexia_id)
                                    <div class="text-xs text-zinc-500">{{ $asset->itexia_id }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-sm">{{ $asset->serial_number }}</flux:table.cell>
                            <flux:table.cell>{{ $asset->owner?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <x-intranet-app-assets::asset-location-display :asset="$asset" tag="div" :show-hint="false" class="text-sm" />
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    <flux:button
                                        :href="route('apps.assets.show', [$asset, 'from' => 'liste'])"
                                        variant="ghost"
                                        size="sm"
                                        icon="eye"
                                        target="_blank"
                                    >
                                        Detail
                                    </flux:button>
                                    <flux:button
                                        :href="route('apps.assets.admin.clarification.resolve', $asset)"
                                        variant="primary"
                                        size="sm"
                                        icon="pencil-square"
                                    >
                                        Bearbeiten
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">
                                Keine Assets in Klärung.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <div>
                {{ $assets->links() }}
            </div>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
