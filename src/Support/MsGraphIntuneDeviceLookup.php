<?php

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Contracts\IntuneDeviceLookupInterface;

/**
 * Adapter: nutzt MsGraph Intune-Service für IntuneDeviceLookupInterface.
 * Wird nur registriert, wenn MsGraphIntuneServiceInterface gebunden ist.
 */
class MsGraphIntuneDeviceLookup implements IntuneDeviceLookupInterface
{
    public function findDeviceIdBySerialNumber(string $serialNumber): ?string
    {
        $device = $this->findDeviceBySerialNumber($serialNumber);

        return $device !== null ? $device['id'] : null;
    }

    /**
     * @return array{id: string, imei: string|null, lastSyncDateTime: string|null}|null
     */
    public function findDeviceBySerialNumber(string $serialNumber): ?array
    {
        $interface = \Hwkdo\MsGraphLaravel\Interfaces\MsGraphIntuneServiceInterface::class;
        if (! app()->bound($interface)) {
            return null;
        }

        $device = app($interface)->findManagedDeviceBySerialNumber($serialNumber);
        if ($device === null) {
            return null;
        }

        return [
            'id' => $device['id'],
            'imei' => $device['imei'] ?? null,
            'lastSyncDateTime' => $device['lastSyncDateTime'] ?? null,
        ];
    }
}
