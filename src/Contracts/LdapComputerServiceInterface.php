<?php

namespace Hwkdo\IntranetAppAssets\Contracts;

use Illuminate\Support\Carbon;

interface LdapComputerServiceInterface
{
    public function exists(string $hostname, string $connection = 'default'): bool;

    /**
     * @return Carbon|false
     */
    public function getLastLogon(string $hostname, string $connection = 'default');

    /**
     * @return Carbon|false
     */
    public function getLastLogonTimestamp(string $hostname, string $connection = 'default');

    public function getItexiaId(string $hostname, string $connection = 'default'): ?string;

    public function setItexiaId(string $hostname, string $itexiaId, string $connection = 'default'): bool;

    /**
     * @return array<int, array{hostname: string, itexia_id: string|null}>
     */
    public function getComputersInOus(string $connection, array $ouDns): array;
}
