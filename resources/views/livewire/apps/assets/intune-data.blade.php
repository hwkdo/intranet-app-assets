<?php

use Livewire\Component;

new class extends Component
{
    /** Seriennummer für MsGraph-Abfrage – wenn leer, wird Hinweis angezeigt. */
    public ?string $serialNumber = null;

    /** Lokale Intune-Geräte-ID aus dem Asset (Anzeige). */
    public ?string $intuneDeviceId = null;

    /** @var array{id: string, deviceName: string|null, userDisplayName: string|null, serialNumber: string|null, imei: string|null, operatingSystem: string|null, osVersion: string|null, model: string|null, phoneNumber: string|null, wiFiMacAddress: string|null, complianceState: string|null}|null */
    public ?array $intuneData = null;

    public ?string $intuneError = null;

    /** true = Daten geladen, false = Fehler/kein Gerät, null = noch nicht geladen */
    public ?bool $loaded = null;

    /** @var array<string, string> */
    protected static array $labels = [
        'id' => 'Intune-ID',
        'deviceName' => 'Gerätename',
        'userDisplayName' => 'Benutzer',
        'serialNumber' => 'Seriennummer',
        'imei' => 'IMEI',
        'operatingSystem' => 'Betriebssystem',
        'osVersion' => 'OS-Version',
        'model' => 'Modell',
        'phoneNumber' => 'Telefonnummer',
        'wiFiMacAddress' => 'WiFi-MAC-Adresse',
        'complianceState' => 'Compliance',
    ];

    public function loadIntuneData(): void
    {
        if ($this->loaded !== null) {
            return;
        }
        $serialNumber = trim((string) $this->serialNumber);
        if ($serialNumber === '') {
            $this->intuneError = 'Seriennummer fehlt – Intune-Daten können nicht abgerufen werden.';
            $this->loaded = false;

            return;
        }
        $interface = 'Hwkdo\MsGraphLaravel\Interfaces\MsGraphIntuneServiceInterface';
        if (! app()->bound($interface)) {
            $this->intuneError = 'Der MsGraph-Intune-Dienst ist nicht verfügbar.';
            $this->loaded = false;

            return;
        }
        try {
            $this->intuneData = app($interface)->findManagedDeviceBySerialNumber($serialNumber);
            $this->loaded = true;
        } catch (\Throwable $e) {
            $this->intuneError = $e->getMessage();
            $this->loaded = false;
        }
    }
};
?>

@placeholder
    <div class="rounded-xl border border-zinc-200 bg-white px-4 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
        <div class="flex items-center gap-2 text-zinc-500">
            <flux:icon.loading variant="micro" />
            <span>Intune-Daten werden geladen…</span>
        </div>
    </div>
@endplaceholder

<div>
    <flux:card class="overflow-hidden transition-colors hover:border-zinc-400/60 dark:hover:border-zinc-500/60">
        <flux:accordion exclusive transition>
            <flux:accordion.item>
                <flux:accordion.heading class="cursor-pointer select-none font-medium">
                    Intune-Daten (MsGraph)
                </flux:accordion.heading>
                <flux:accordion.content>
                <div wire:intersect.once="loadIntuneData" class="min-h-[2rem]">
                    @if($loaded === null)
                        <div class="flex items-center gap-2 text-zinc-500">
                            <flux:icon.loading variant="micro" wire:loading.delay.shortest />
                            <span wire:loading.delay.shortest>Lade Intune-Daten…</span>
                        </div>
                    @elseif($intuneError)
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:callout.text>{{ $intuneError }}</flux:callout.text>
                        </flux:callout>
                    @elseif($intuneData !== null)
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <dt class="font-semibold">Geräte-ID (lokal)</dt>
                            <dd class="font-mono text-xs">{{ $intuneDeviceId ?? 'N/A' }}</dd>
                            @foreach(static::$labels as $key => $label)
                                @if(isset($intuneData[$key]) && (string) $intuneData[$key] !== '')
                                    <dt class="font-semibold">{{ $label }}</dt>
                                    <dd @if(in_array($key, ['id', 'serialNumber', 'imei', 'wiFiMacAddress', 'phoneNumber'], true)) class="font-mono text-xs" @endif>{{ $intuneData[$key] }}</dd>
                                @endif
                            @endforeach
                        </dl>
                    @else
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:callout.text>Kein Gerät mit dieser Seriennummer in Intune gefunden.</flux:callout.text>
                        </flux:callout>
                    @endif
                </div>
                </flux:accordion.content>
            </flux:accordion.item>
        </flux:accordion>
    </flux:card>
</div>
