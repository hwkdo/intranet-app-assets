@props([
    'users',
    'userIdWireModel',
    'deviceTypeWireModel',
    'locationWireModel',
    'userIdErrorName',
    'deviceTypeErrorName' => null,
    'locationErrorName' => null,
    'userId' => '',
])

@php
    $showUnownedFields = \Hwkdo\IntranetAppAssets\Support\AssetOwnerChoice::isNone($userId);
@endphp

<flux:fieldset {{ $attributes->class(['sm:col-span-2']) }}>
    <flux:legend>
        Zuordnung
        <flux:badge size="sm" color="red">Pflicht</flux:badge>
    </flux:legend>
    <flux:description>
        Mit Besitzer = persönliches Gerät (Standort über Besitzer-Stammdaten). Ohne Besitzer: Gerätetyp und physischer Standort.
    </flux:description>

    <div class="space-y-6">
        <div @class([
            'grid gap-6',
            'grid-cols-1 sm:grid-cols-2' => $showUnownedFields,
            'max-w-xl' => ! $showUnownedFields,
        ])>
            <flux:field>
                <flux:label>Besitzer</flux:label>
                <flux:select variant="listbox" searchable wire:model.live="{{ $userIdWireModel }}" placeholder="Bitte wählen…">
                    <flux:select.option value="">Bitte wählen…</flux:select.option>
                    <flux:select.option value="{{ \Hwkdo\IntranetAppAssets\Support\AssetOwnerChoice::None }}">Kein Besitzer</flux:select.option>
                    @foreach($users as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="{{ $userIdErrorName }}" />
            </flux:field>

            @if($showUnownedFields)
                <x-intranet-app-assets::unowned-device-type-select
                    :wire-model="$deviceTypeWireModel"
                    :error-name="$deviceTypeErrorName"
                />
            @endif
        </div>

        @if($showUnownedFields)
            <flux:field class="max-w-xl">
                <flux:label>Standort <flux:badge size="sm" color="red">Pflicht</flux:badge></flux:label>
                <flux:input wire:model="{{ $locationWireModel }}" placeholder="z. B. IT-Lager, Büro 2.13, Werkstatt …" />
                @if($locationErrorName)
                    <flux:error name="{{ $locationErrorName }}" />
                @endif
            </flux:field>
        @endif
    </div>
</flux:fieldset>
