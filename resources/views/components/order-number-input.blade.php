@props([
    'name' => 'order_number',
    'placeholder' => 'z. B. 112345678',
    'required' => false,
    'requirementHint' => null,
])

<flux:field>
    <flux:label>
        Bestellnummer
        <span class="ml-1 font-normal text-slate-600 dark:text-white/80">(BEN)</span>
        @if($required)
            <x-intranet-app-assets::hwk-badge size="sm" color="red">Pflicht</x-intranet-app-assets::hwk-badge>
        @endif
    </flux:label>
    <flux:input
        {{ $attributes->merge(['placeholder' => $placeholder]) }}
    />
    <flux:description class="mt-1 text-sm text-slate-600 dark:text-white/80">
        @if(filled($requirementHint))
            {{ $requirementHint }}{{ ' ' }}
        @endif
        {{ \Hwkdo\IntranetAppAssets\Support\AssetCreateWizardCopy::orderNumberFormatDescription() }}
    </flux:description>
    <flux:error :name="$name" />
</flux:field>
