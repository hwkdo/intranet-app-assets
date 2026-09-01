<div>
    <x-intranet-app-assets::assets-layout
        heading="Assets vermisst"
        subheading="Als vermisst markierte Assets"
    >
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Asset</flux:table.column>
                <flux:table.column>Seriennummer</flux:table.column>
                <flux:table.column>Typ</flux:table.column>
                <flux:table.column>Standort</flux:table.column>
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
                        <flux:table.cell class="font-mono text-sm">{{ $asset->serial_number }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->type?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ filled($asset->location) ? $asset->location : '—' }}</flux:table.cell>
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
                                    :href="route('apps.assets.edit', [$asset, 'from' => 'liste'])"
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
                        <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">
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
