<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\ConfigmgrLaravel\ConfigmgrLaravel;
use Hwkdo\IntranetAppAssets\Support\ConfigmgrSerialNumber;
use Livewire\Component;

class ConfigmgrData extends Component
{
    public ?int $assetId = null;

    public ?string $computerName = null;

    public ?string $serialNumber = null;

    public ?string $configmgrSerialNumber = null;

    /** @var array<int, object{rechnername: string|null, smbios_guid: string|null, last_logon_user: string|null, mac_adresse: string|null}>|null */
    public ?array $configmgrRows = null;

    public ?string $configmgrError = null;

    public function mount(
        ?int $assetId = null,
        ?string $computerName = null,
        ?string $serialNumber = null,
        ?string $configmgrSerialNumber = null,
    ): void {
        $this->assetId = $assetId;
        $this->computerName = $computerName;
        $this->serialNumber = $serialNumber;
        $this->configmgrSerialNumber = $configmgrSerialNumber;

        $computerName = trim((string) $computerName ?? '');
        if ($computerName === '') {
            $this->configmgrError = 'Kein Computername (Asset-Name) vorhanden.';

            return;
        }

        if (! class_exists(ConfigmgrLaravel::class)) {
            $this->configmgrError = 'ConfigMgr-Paket nicht verfügbar.';

            return;
        }

        try {
            $configmgr = app(ConfigmgrLaravel::class);
            $this->configmgrRows = $configmgr->getSystemDataByComputerName($computerName);
        } catch (\Throwable $e) {
            $this->configmgrError = $e->getMessage();
        }
    }

    public function configmgrSerialMismatches(): bool
    {
        return ConfigmgrSerialNumber::mismatches($this->serialNumber, $this->configmgrSerialNumber);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.configmgr-data');
    }
}
