<x-mail::message>
# Rechnungsnummer ergänzt

Zu einem Asset mit Itexia-Verknüpfung wurde die **Rechnungsnummer** hinterlegt.

**Ergänzt von:** {{ $actorName }}

**Rechnungsnr.:** {{ $invoiceNumber }}

@if (filled($invoiceUrl))
<x-mail::button :url="$invoiceUrl">Rechnung in d3One öffnen</x-mail::button>
@endif

---

**Typ:** {{ $typeName !== '' ? $typeName : '—' }}

**Hersteller:** {{ $vendorName !== '' ? $vendorName : '—' }}

**Modell:** {{ $modelName !== '' ? $modelName : '—' }}

**Seriennummer:** {{ $serialNumber !== '' ? $serialNumber : '—' }}

**Itexia-ID (Barcode):** {{ $itexiaId }}

**Itexia-UUID (Seventhings):** {{ $itexiaUuid ?? '—' }}

<x-mail::button :url="$assetUrl">Asset im Intranet öffnen</x-mail::button>
</x-mail::message>
