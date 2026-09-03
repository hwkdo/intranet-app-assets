<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Contracts;

use App\Models\User;

interface LdapPasswordVerifierInterface
{
    /**
     * Prüft das Active-Directory-Passwort eines Intranet-Users (nicht der aktuellen Session).
     */
    public function verify(User $user, string $password): bool;
}
