<?php

namespace Hwkdo\IntranetAppAssets\Contracts;

interface IntuneDeviceLookupInterface
{
    /**
     * Sucht ein Intune-Gerät anhand der Seriennummer und gibt die Intune-Geräte-ID zurück.
     */
    public function findDeviceIdBySerialNumber(string $serialNumber): ?string;

    /**
     * Sucht ein Intune-Gerät anhand der Seriennummer und gibt ID sowie IMEI zurück (für Sync).
     *
     * @return array{id: string, imei: string|null}|null
     */
    public function findDeviceBySerialNumber(string $serialNumber): ?array;
}
