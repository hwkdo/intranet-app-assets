<div>
    <x-intranet-app-assets::assets-layout
        heading="Falsche Seriennummern"
        subheading="Assets, deren Seriennummer von der SCCM-BIOS-Seriennummer abweicht"
    >
        <flux:table>
            <flux:table.columns>
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'display_name', 'label' => 'Asset'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'serial_number', 'label' => 'Seriennummer'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'configmgr_serial_number', 'label' => 'SCCM-Seriennummer'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'type', 'label' => 'Typ'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'owner', 'label' => 'Besitzer'])
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($assets as $asset)
                    <flux:table.row wire:key="configmgr-serial-mismatch-{{ $asset->id }}">
                        <flux:table.cell>
                            <div class="font-medium">{{ $asset->display_name }}</div>
                            @if($asset->itexia_id)
                                <div class="text-xs text-zinc-500">{{ $asset->itexia_id }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <x-intranet-app-assets::serial-number-diff
                                :serial="$asset->serial_number"
                                :other="$asset->configmgr_serial_number"
                                side="asset"
                            />
                        </flux:table.cell>
                        <flux:table.cell>
                            <x-intranet-app-assets::serial-number-diff
                                :serial="$asset->serial_number"
                                :other="$asset->configmgr_serial_number"
                                side="sccm"
                            />
                        </flux:table.cell>
                        <flux:table.cell>{{ $asset->type?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->owner?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-1 whitespace-nowrap">
                                <flux:button
                                    wire:click="adoptConfigmgrSerial({{ $asset->id }})"
                                    wire:confirm="Seriennummer von „{{ $asset->serial_number }}“ auf „{{ $asset->configmgr_serial_number }}“ aus SCCM übernehmen?"
                                    variant="primary"
                                    size="sm"
                                    icon="arrow-down-tray"
                                >
                                    Aus SCCM übernehmen
                                </flux:button>
                                <flux:button
                                    :href="route('apps.assets.show', [$asset, 'from' => 'falsche-seriennummern'])"
                                    variant="ghost"
                                    size="sm"
                                    icon="eye"
                                >
                                    Detail
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">
                            Keine abweichenden SCCM-Seriennummern.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $assets->links() }}
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
