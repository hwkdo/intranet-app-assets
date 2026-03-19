<div>
<x-intranet-app-assets::assets-layout
    heading="Fehlende Rechnung"
    subheading="Assets mit „Rechnungsnr. noch nicht bekannt“ – bitte Rechnungsnummer nachtragen"
>
    <div class="space-y-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Modell / Name</flux:table.column>
                <flux:table.column>Seriennummer</flux:table.column>
                <flux:table.column>Typ</flux:table.column>
                <flux:table.column>Hersteller</flux:table.column>
                <flux:table.column>Angelegt von</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($assets as $asset)
                    <flux:table.row wire:key="fehlende-rechnung-{{ $asset->id }}">
                        <flux:table.cell>
                            <div class="font-medium">{{ $asset->display_name }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $asset->serial_number }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->type?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->vendor?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->createdBy?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('apps.assets.edit', $asset) }}" variant="ghost" size="sm" icon="pencil">
                                Bearbeiten
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500 py-8">
                            Keine Assets mit fehlender Rechnungsnummer.
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
