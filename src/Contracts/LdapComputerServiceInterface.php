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
}
