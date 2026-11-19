<?php

use Livewire\Component;

new class extends Component
{
    /** Seriennummer für MsGraph-Abfrage – wenn leer, wird Hinweis angezeigt. */
    public ?string $serialNumber = null;

    /** Lokale Intune-Geräte-ID aus dem Asset (Anzeige). */
    public ?string $intuneDeviceId = null;

    /** @var array{id: string, deviceName: string|null, userDisplayName: string|null, serialNumber: string|null, imei: string|null, operatingSystem: string|null, osVersion: string|null, model: string|null, phoneNumber: string|null, wiFiMacAddress: string|null, complianceState: string|null, lastSyncDateTime: string|null}|null */
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
        'lastSyncDateTime' => 'Letzter Check-in',
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
    <div class="rounded-xl border border-[#d0e3f9] bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-white/5">
        <div class="flex items-center gap-2 text-slate-600 dark:text-white/70">
            <flux:icon.loading variant="micro" />
            <span>Intune-Daten werden geladen…</span>
        </div>
    </div>
@endplaceholder

<div>
    <section class="glass-card overflow-hidden transition-colors hover:border-[#073070]/20 dark:hover:border-white/20">
        <flux:accordion exclusive transition>
            <flux:accordion.item>
                <flux:accordion.heading class="cursor-pointer select-none font-medium">
                    Intune-Daten (MsGraph)
                </flux:accordion.heading>
                <flux:accordion.content>
                <div wire:intersect.once="loadIntuneData" class="min-h-[2rem]">
                    @if($loaded === null)
                        <div class="flex items-center gap-2 text-slate-600 dark:text-white/70">
                            <flux:icon.loading variant="micro" wire:loading.delay.shortest />
                            <span wire:loading.delay.shortest>Lade Intune-Daten…</span>
                        </div>
                    @elseif($intuneError)
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:callout.text>{{ $intuneError }}</flux:callout.text>
                        </flux:callout>
                    @elseif($intuneData !== null)
                        <dl class="hwk-rows">
                            <dt class="font-semibold">Geräte-ID (lokal)</dt>
                            <dd class="font-mono text-xs">{{ $intuneDeviceId ?? 'N/A' }}</dd>
                            @foreach(static::$labels as $key => $label)
                                @if(isset($intuneData[$key]) && (string) $intuneData[$key] !== '')
                                    <dt class="font-semibold">{{ $label }}</dt>
                                    <dd @if(in_array($key, ['id', 'serialNumber', 'imei', 'wiFiMacAddress', 'phoneNumber'], true)) class="font-mono text-xs" @endif>
                                        @if($key === 'lastSyncDateTime')
                                            {{ \Carbon\Carbon::parse($intuneData[$key])->format('d.m.Y H:i') }}
                                        @else
                                            {{ $intuneData[$key] }}
                                        @endif
                                    </dd>
                                @endif
                            @endforeach
                        </dl>
                    @else
                        <x-intranet-app-assets::hwk-empty bare icon="device-phone-mobile" title="Kein Gerät gefunden">
                            Kein Gerät mit dieser Seriennummer in Intune gefunden.
                        </x-intranet-app-assets::hwk-empty>
                    @endif
                </div>
                </flux:accordion.content>
            </flux:accordion.item>
        </flux:accordion>
    </section>
</div>
