<div>
<x-intranet-app-assets::assets-layout heading="Yubikeys" subheading="Aktive Benutzer und zugewiesene Yubikeys">
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 flex-wrap items-center gap-3">
                <div class="min-w-64 max-w-sm flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suchen nach SN, Benutzername, Vor- oder Nachname…"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>
                <flux:switch wire:model.live="onlyWithoutYubikey" label="Nur Benutzer ohne Yubikey" />
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
                <flux:table.column>Typ</flux:table.column>
                <flux:table.column>Hersteller</flux:table.column>
                <flux:table.column>Besitzer</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($users as $user)
                    @php
                        /** @var \Illuminate\Support\Collection<int, \Hwkdo\IntranetAppAssets\Models\Asset> $yubikeys */
                        $yubikeys = $user->relationLoaded('yubikeys') ? $user->yubikeys : collect();
                    @endphp

                    @if($yubikeys->isEmpty())
                        <flux:table.row wire:key="yubikey-user-{{ $user->id }}">
                            <flux:table.cell class="text-zinc-500">—</flux:table.cell>
                            <flux:table.cell class="text-zinc-500">—</flux:table.cell>
                            <flux:table.cell class="text-zinc-500">—</flux:table.cell>
                            <flux:table.cell class="text-zinc-500">—</flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium">{{ $user->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $user->username }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">Kein Yubikey</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                        </flux:table.row>
                    @else
                        @foreach($yubikeys as $yubikey)
                            <flux:table.row wire:key="yubikey-user-{{ $user->id }}-asset-{{ $yubikey->id }}">
                                <flux:table.cell>
                                    <div class="font-medium">{{ $yubikey->display_name }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="font-mono text-sm">{{ $yubikey->serial_number }}</flux:table.cell>
                                <flux:table.cell>{{ $yubikey->type?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $yubikey->vendor?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="font-medium">{{ $user->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $user->username }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="green" size="sm">Zugewiesen</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button href="{{ route('apps.assets.show', [$yubikey, 'from' => 'yubikeys']) }}" variant="ghost" size="sm" icon="eye" wire:navigate />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    @endif
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500 py-8">
                            @if($onlyWithoutYubikey)
                                Keine aktiven Benutzer ohne Yubikey gefunden.
                            @elseif(filled($search))
                                Keine Treffer für „{{ $search }}“.
                            @else
                                Keine aktiven Benutzer gefunden.
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div>
            {{ $users->links() }}
        </div>
    </div>
</x-intranet-app-assets::assets-layout>
</div>
