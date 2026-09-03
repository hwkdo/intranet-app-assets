<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Contracts\LdapPasswordVerifierInterface;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

class LdapRecordPasswordVerifier implements LdapPasswordVerifierInterface
{
    public function verify(User $user, string $password): bool
    {
        $username = trim((string) $user->username);
        if ($username === '' || $password === '') {
            return false;
        }

        $ldapUser = LdapUser::findBy('samaccountname', $username);
        if ($ldapUser === null) {
            return false;
        }

        return Container::getDefaultConnection()->auth()->attempt($ldapUser->getDn(), $password);
    }
}
