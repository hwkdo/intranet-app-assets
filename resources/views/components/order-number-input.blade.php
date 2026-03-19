@props([
    'name' => 'order_number',
    'placeholder' => 'Optional',
    'required' => false,
])

<flux:field>
    <flux:label>
        Bestellnummer
        <span class="ml-1 font-normal text-zinc-500 dark:text-zinc-200">(BEN)</span>
        @if($required)
            <flux:badge size="sm" color="red">Pflicht</flux:badge>
        @endif
    </flux:label>
    <flux:input
        {{ $attributes->merge(['placeholder' => $placeholder]) }}
    />
    <flux:description class="mt-1 text-sm text-zinc-500 dark:text-zinc-200">
        {{ \Hwkdo\IntranetAppAssets\Services\LegacyOrderNumberValidationService::getFormatDescription() }} Wird gegen das Bestellsystem geprüft.
    </flux:description>
    <flux:error :name="$name" />
</flux:field>
