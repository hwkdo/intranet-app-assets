@props([
    'name' => 'invoice_number',
    'placeholder' => 'z. B. T12345',
    'required' => false,
    'requirementHint' => null,
])

<flux:field>
    <flux:label>
        Rechnungsnummer
        <span class="ml-1 font-normal text-slate-600 dark:text-white/80">(D3-Dokument-ID / T-Nummer)</span>
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
        Format: T gefolgt von Ziffern (z. B. T12345). Wird in D3 geprüft (Zahlungsbeleg Typ Rechnung).
    </flux:description>
    <flux:error :name="$name" />
</flux:field>
