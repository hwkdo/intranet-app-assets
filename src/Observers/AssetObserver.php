<?php

namespace Hwkdo\IntranetAppAssets\Observers;

use Hwkdo\IntranetAppAssets\Contracts\LdapComputerServiceInterface;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Illuminate\Support\Facades\Log;

class AssetObserver
{
    public function created(Asset $asset): void
    {
        $this->syncItexiaIdToLdapIfNeeded($asset);
    }

    public function saved(Asset $asset): void
    {
        if ($asset->wasChanged('itexia_id')) {
            $this->syncItexiaIdToLdapIfNeeded($asset);
        }
    }

    private function syncItexiaIdToLdapIfNeeded(Asset $asset): void
    {
        if (! $this->appBound(LdapComputerServiceInterface::class)) {
            return;
        }

        if (! filled($asset->itexia_id) || ! filled($asset->name) || ! filled($asset->domain_connection)) {
            return;
        }

        $asset->loadMissing('type');
        if (! $asset->type?->is_domain_object) {
            return;
        }

        try {
            $ldap = app(LdapComputerServiceInterface::class);
            $ldap->setItexiaId($asset->name, $asset->itexia_id, $asset->domain_connection);
        } catch (\Throwable $e) {
            Log::error('AssetObserver: Fehler beim Setzen der Itexia-ID in LDAP.', [
                'asset_id' => $asset->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function appBound(string $abstract): bool
    {
        return app()->bound($abstract);
    }
}
