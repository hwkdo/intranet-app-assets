<x-mail::message>
# Asset mit Itexia-ID angelegt

Im Intranet wurde ein Asset mit Itexia-Verknüpfung **angelegt**.

**Angelegt von:** {{ $actorName }}

**Standort:** {{ filled($location) ? $location : '—' }}

---

**Typ:** {{ $typeName !== '' ? $typeName : '—' }}

**Hersteller:** {{ $vendorName !== '' ? $vendorName : '—' }}

**Modell:** {{ $modelName !== '' ? $modelName : '—' }}

**Seriennummer:** {{ $serialNumber !== '' ? $serialNumber : '—' }}

**Itexia-ID (Barcode):** {{ $itexiaId }}

**Itexia-UUID (Seventhings):** {{ $itexiaUuid ?? '—' }}

**Rechnungsnr.:** {{ filled($invoiceNumber) ? $invoiceNumber : '—' }}

@if (filled($invoiceNumber))
@if (filled($invoiceUrl))
<x-mail::button :url="$invoiceUrl">Rechnung in d3One öffnen</x-mail::button>
@endif
@else
Die Rechnungsnummer liegt noch nicht vor und wird später ergänzt. Sobald sie hinterlegt ist, folgt eine weitere E-Mail mit der Nummer und dem Link zu d3One.
@endif

---

**Itexia-Ist-Raum:** {{ $roomSyncSummary }}

<x-mail::button :url="$assetUrl">Asset im Intranet öffnen</x-mail::button>
</x-mail::message>
