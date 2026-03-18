<?php

namespace Hwkdo\IntranetAppAssets\Contracts;

interface IntuneDeviceLookupInterface
{
    /**
     * Sucht ein Intune-Gerät anhand der Seriennummer und gibt die Intune-Geräte-ID zurück.
     */
    public function findDeviceIdBySerialNumber(string $serialNumber): ?string;

    /**
     * Sucht ein Intune-Gerät anhand der Seriennummer und gibt ID, IMEI und Last-Check-in zurück (für Sync).
     *
     * @return array{id: string, imei: string|null, lastSyncDateTime: string|null}|null
     */
    public function findDeviceBySerialNumber(string $serialNumber): ?array;
}
