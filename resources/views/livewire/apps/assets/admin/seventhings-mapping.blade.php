<div class="space-y-4">
    <flux:card>
        <flux:heading size="lg">Zuordnung definieren</flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            Ordnen Sie lokale Asset-Felder den Itexia/Seventhings-Feldern zu. Diese Zuordnung wird beim „Itexia-Daten vergleichen“ auf der Asset-Detailseite verwendet.
        </flux:text>

        <form wire:submit="addMapping" class="mt-4 flex flex-wrap items-end gap-3">
            <flux:field class="min-w-[200px]">
                <flux:label>Lokales Feld</flux:label>
                <flux:select wire:model="local_attribute" placeholder="Feld wählen">
                    @foreach(\Hwkdo\IntranetAppAssets\SeventhingsMappingConfig::localAttributes() as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field class="min-w-[200px]">
                <flux:label>Itexia-Feld</flux:label>
                <flux:select wire:model="itexia_attribute" placeholder="Feld wählen">
                    @foreach(\Hwkdo\IntranetAppAssets\SeventhingsMappingConfig::itexiaAttributes() as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:button type="submit" variant="primary" size="sm">Zuordnung hinzufügen</flux:button>
        </form>
    </flux:card>

    <flux:card>
        <flux:heading size="lg">Aktuelle Zuordnungen</flux:heading>
        @if($this->mappings->isEmpty())
            <flux:text class="mt-2 text-zinc-500">Noch keine Zuordnungen definiert.</flux:text>
        @else
            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Lokales Feld</flux:table.column>
                    <flux:table.column>Itexia-Feld</flux:table.column>
                    <flux:table.column class="w-24">Aktion</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->mappings as $mapping)
                        <flux:table.row>
                            <flux:table.cell>
                                {{ \Hwkdo\IntranetAppAssets\SeventhingsMappingConfig::localAttributes()[$mapping->local_attribute] ?? $mapping->local_attribute }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ \Hwkdo\IntranetAppAssets\SeventhingsMappingConfig::itexiaAttributes()[$mapping->itexia_attribute] ?? $mapping->itexia_attribute }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button wire:click="deleteMapping({{ $mapping->id }})" wire:confirm="Zuordnung wirklich entfernen?" variant="ghost" size="sm" icon="trash" color="red">
                                    Entfernen
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
