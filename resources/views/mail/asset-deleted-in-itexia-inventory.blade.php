<x-mail::message>
# Asset im Intranet gelöscht

Ein Asset mit Itexia-Verknüpfung wurde im Intranet **gelöscht** (Soft Delete).

**Gelöscht von:** {{ $deletedByName }}

**Grund:**  
{{ $deleteReason }}

---

**Typ:** {{ $typeName !== '' ? $typeName : '—' }}

**Hersteller:** {{ $vendorName !== '' ? $vendorName : '—' }}

**Modell:** {{ $modelName !== '' ? $modelName : '—' }}

**Itexia-ID (Barcode):** {{ $itexiaId }}

**Itexia-UUID (Seventhings):** {{ $itexiaUuid ?? '—' }}

<x-mail::subcopy>
Diese Nachricht wurde automatisch erzeugt, weil der Datensatz in Seventhings zum Zeitpunkt der Löschung noch existierte.
</x-mail::subcopy>
</x-mail::message>
