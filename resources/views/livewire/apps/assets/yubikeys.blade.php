<div>
<x-intranet-app-assets::assets-layout heading="Yubikeys" subheading="Aktive Benutzer und zugewiesene Yubikeys">
    <div class="space-y-4">
        <div class="glass-card sticky top-3 z-20 p-3 shadow-lg shadow-[#073070]/10 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" style="--glass-opacity: 0.92; overflow: visible">
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

        <div class="glass-card overflow-hidden">
<flux:table>
            <flux:table.columns>
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'display_name', 'label' => 'Modell / Name'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'serial_number', 'label' => 'Seriennummer'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'type', 'label' => 'Typ'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'vendor', 'label' => 'Hersteller'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'owner', 'label' => 'Besitzer'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'status', 'label' => 'Status'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'created_at', 'label' => 'Angelegt', 'align' => 'end'])
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
                            <flux:table.cell class="text-slate-600 dark:text-white/70">—</flux:table.cell>
                            <flux:table.cell class="text-slate-600 dark:text-white/70">—</flux:table.cell>
                            <flux:table.cell class="text-slate-600 dark:text-white/70">—</flux:table.cell>
                            <flux:table.cell class="text-slate-600 dark:text-white/70">—</flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium">{{ $user->name }}</div>
                                <div class="text-xs text-slate-600 dark:text-white/70">{{ $user->username }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <x-intranet-app-assets::hwk-badge color="zinc" size="sm">Kein Yubikey</x-intranet-app-assets::hwk-badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-end text-slate-600 dark:text-white/70">—</flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                        </flux:table.row>
                    @else
                        @foreach($yubikeys as $yubikey)
                            <flux:table.row wire:key="yubikey-user-{{ $user->id }}-asset-{{ $yubikey->id }}">
                                <flux:table.cell>
                                    <div class="font-medium">{{ $yubikey->display_name }}</div>
                                </flux:table.cell>
                                @include('intranet-app-assets::partials.serial-number-cell', ['serialNumber' => $yubikey->serial_number])
                                <flux:table.cell>{{ $yubikey->type?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $yubikey->vendor?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="font-medium">{{ $user->name }}</div>
                                    <div class="text-xs text-slate-600 dark:text-white/70">{{ $user->username }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-intranet-app-assets::hwk-badge color="green" size="sm">Zugewiesen</x-intranet-app-assets::hwk-badge>
                                </flux:table.cell>
                                @include('intranet-app-assets::partials.created-at-cell', ['asset' => $yubikey])
                                <flux:table.cell>
                                    <flux:button href="{{ route('apps.assets.show', [$yubikey, 'from' => 'yubikeys']) }}" variant="ghost" size="sm" icon="eye" wire:navigate />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    @endif
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="px-3 py-3">
<x-intranet-app-assets::hwk-empty bare icon="magnifying-glass">
@if($onlyWithoutYubikey)
                                Keine aktiven Benutzer ohne Yubikey gefunden.
                            @elseif(filled($search))
                                Keine Treffer für „{{ $search }}“.
                            @else
                                Keine aktiven Benutzer gefunden.
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
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-intranet-app-assets::assets-layout>
</div>
