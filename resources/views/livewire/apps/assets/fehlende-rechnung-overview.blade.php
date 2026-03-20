<div>
<x-intranet-app-assets::assets-layout
    heading="Fehlende Rechnung"
    subheading="Assets mit „Rechnungsnr. noch nicht bekannt“ (beim Anlegen angegeben oder nach Ablauf der automatischen D3-Suche) – bitte Rechnungsnummer nachtragen"
>
    <div class="space-y-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Modell / Name</flux:table.column>
                <flux:table.column>Seriennummer</flux:table.column>
                <flux:table.column>Rechnungsnr.</flux:table.column>
                <flux:table.column>BEN</flux:table.column>
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
                        <flux:table.cell class="font-mono text-sm">{{ filled($asset->invoice_number) ? $asset->invoice_number : '—' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ filled($asset->order_number) ? $asset->order_number : '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->createdBy?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('apps.assets.show', $asset) }}" variant="ghost" size="sm" icon="eye">
                                Anzeigen
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
