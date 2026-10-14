<x-mail::message>
# Itexia-UUID verknüpft

Das Asset wurde in Seventhings gefunden. Die **Itexia-UUID** wurde im Intranet gespeichert.

**Itexia-ID (Barcode):** {{ $itexiaId }}

**Itexia-UUID (Seventhings):** {{ $itexiaUuid }}

---

**Typ:** {{ $typeName !== '' ? $typeName : '—' }}

**Hersteller:** {{ $vendorName !== '' ? $vendorName : '—' }}

**Modell:** {{ $modelName !== '' ? $modelName : '—' }}

**Seriennummer:** {{ $serialNumber !== '' ? $serialNumber : '—' }}

**Rechnungsnr.:** {{ filled($invoiceNumber) ? $invoiceNumber : '—' }}

@if (filled($invoiceNumber) && filled($invoiceUrl))
<x-mail::button :url="$invoiceUrl">Rechnung in d3One öffnen</x-mail::button>
@endif

<x-mail::button :url="$assetUrl">Asset im Intranet öffnen</x-mail::button>
</x-mail::message>
