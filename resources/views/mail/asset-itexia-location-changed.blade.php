<x-mail::message>
# Standort eines Assets mit Itexia-ID geändert

Der Standort eines Assets mit Itexia-Verknüpfung hat sich geändert. Das umfasst auch Übergaben und Rückgaben.

**Geändert von:** {{ $actorName }}

**Bisheriger Standort:** {{ filled($previousLocation) ? $previousLocation : '—' }}

**Neuer Standort:** {{ filled($newLocation) ? $newLocation : '—' }}

---

**Typ:** {{ $typeName !== '' ? $typeName : '—' }}

**Hersteller:** {{ $vendorName !== '' ? $vendorName : '—' }}

**Modell:** {{ $modelName !== '' ? $modelName : '—' }}

**Seriennummer:** {{ $serialNumber !== '' ? $serialNumber : '—' }}

**Itexia-ID (Barcode):** {{ $itexiaId }}

**Itexia-UUID (Seventhings):** {{ $itexiaUuid ?? '—' }}

---

**Itexia-Ist-Raum:** {{ $roomSyncSummary }}

<x-mail::button :url="$assetUrl">Asset im Intranet öffnen</x-mail::button>
</x-mail::message>
