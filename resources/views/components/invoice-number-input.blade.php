@props([
    'name' => 'invoice_number',
    'placeholder' => 'Optional',
    'required' => false,
])

<flux:field>
    <flux:label>
        Rechnungsnummer
        <span class="ml-1 font-normal text-zinc-500 dark:text-zinc-200">(D3-Dokument-ID / T-Nummer)</span>
        @if($required)
            <flux:badge size="sm" color="red">Pflicht</flux:badge>
        @endif
    </flux:label>
    <flux:input
        {{ $attributes->merge(['placeholder' => $placeholder]) }}
    />
    <flux:description class="mt-1 text-sm text-zinc-500 dark:text-zinc-200">
        Format: T gefolgt von Ziffern (z. B. T12345). Wird in D3 geprüft (Zahlungsbeleg Typ Rechnung).
    </flux:description>
    <flux:error :name="$name" />
</flux:field>
