@props([
    'wireModel',
    'errorName' => null,
    'required' => true,
    'showLabel' => true,
])

<flux:field {{ $attributes }}>
    @if($showLabel)
        <flux:label>
            Gerätetyp
            @if($required)
                <flux:badge size="sm" color="red">Pflicht</flux:badge>
            @endif
        </flux:label>
    @endif
    <flux:select variant="listbox" wire:model="{{ $wireModel }}" placeholder="Bitte wählen…">
        <flux:select.option value="">Bitte wählen…</flux:select.option>
        <flux:select.option
            value="{{ \Hwkdo\IntranetAppAssets\Support\AssetUnownedDeviceType::Pool }}"
            label="Auf Lager (Pool-Gerät)"
            icon="archive-box"
            description="Freies Gerät zur Vergabe im IT-Lager."
        />
        <flux:select.option
            value="{{ \Hwkdo\IntranetAppAssets\Support\AssetUnownedDeviceType::Shared }}"
            label="Gemeinschaftsgerät"
            icon="building-office-2"
            description="Nicht personen-bezogene Assets wie Teilnehmer-PCs, Werkstattgeräte, Gemeinschaftsdrucker, usw."
        />
    </flux:select>
    @if($errorName)
        <flux:error name="{{ $errorName }}" />
    @endif
</flux:field>
