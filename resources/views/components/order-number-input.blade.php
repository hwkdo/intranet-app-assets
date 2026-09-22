@props([
    'name' => 'order_number',
    'placeholder' => 'z. B. 112345678',
    'required' => false,
    'requirementHint' => null,
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
        @if(filled($requirementHint))
            {{ $requirementHint }}{{ ' ' }}
        @endif
        {{ \Hwkdo\IntranetAppAssets\Support\AssetCreateWizardCopy::orderNumberFormatDescription() }}
    </flux:description>
    <flux:error :name="$name" />
</flux:field>
