<div>
    <x-intranet-app-assets::assets-layout
        heading="Assets vermisst"
        subheading="Als vermisst markierte Assets"
    >
        <flux:table>
            <flux:table.columns>
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'display_name', 'label' => 'Asset'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'serial_number', 'label' => 'Seriennummer'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'type', 'label' => 'Typ'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'owner', 'label' => 'Besitzer (laut System)'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'location', 'label' => 'Standort'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'created_at', 'label' => 'Angelegt', 'align' => 'end'])
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($assets as $asset)
                    <flux:table.row wire:key="missing-asset-{{ $asset->id }}">
                        <flux:table.cell>
                            <div class="font-medium">{{ $asset->display_name }}</div>
                            @if($asset->itexia_id)
                                <div class="text-xs text-zinc-500">{{ $asset->itexia_id }}</div>
                            @endif
                        </flux:table.cell>
                        @include('intranet-app-assets::partials.serial-number-cell', ['serialNumber' => $asset->serial_number])
                        <flux:table.cell>{{ $asset->type?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->owner?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <x-intranet-app-assets::asset-location-display :asset="$asset" tag="div" :show-hint="false" class="text-sm" />
                        </flux:table.cell>
                        @include('intranet-app-assets::partials.created-at-cell', ['asset' => $asset])
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
                                    :href="route('apps.assets.admin.missing.resolve', $asset)"
                                    variant="primary"
                                    size="sm"
                                    icon="check-circle"
                                >
                                    Auflösen
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500">
                            Keine vermissten Assets.
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
