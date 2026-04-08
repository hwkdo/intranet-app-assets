<?php

namespace Hwkdo\IntranetAppAssets\Observers;

use Hwkdo\IntranetAppAssets\Contracts\LdapComputerServiceInterface;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Hwkdo\IntranetAppAssets\Support\AssetAuditDiffBuilder;
use Illuminate\Support\Facades\Log;

class AssetObserver
{
    /**
     * Felder, deren Änderungen im Verlauf protokolliert werden.
     *
     * Nicht enthalten u. a.: last_logon, intune_last_check_in — reine Hintergrund-/Sync-Werte
     * (Domain/Horizon, Intune-Sync), keine fachlich relevanten Asset-Stammdaten-Änderungen.
     *
     * @var list<string>
     */
    private const AUDITED_FIELDS = [
        'serial_number',
        'model',
        'asset_type_id',
        'asset_vendor_id',
        'user_id',
        'name',
        'location',
        'is_clarification',
        'is_missing',
        'itexia_id',
        'itexia_uuid',
        'order_number',
        'invoice_number',
        'invoice_number_pending',
        'domain_connection',
        'intune_device_id',
        'imei',
        'configmgr_serial_number',
        'configmgr_last_sync_at',
        'configmgr_mac_addresses',
        'smbios_guid',
    ];

    public function created(Asset $asset): void
    {
        $this->syncItexiaIdToLdapIfNeeded($asset);
    }

    public function updated(Asset $asset): void
    {
        $changes = $asset->getChanges();
        unset($changes['updated_at'], $changes['created_at']);

        if ($changes === []) {
            return;
        }

        $diff = AssetAuditDiffBuilder::build($asset->getOriginal(), $changes, self::AUDITED_FIELDS);
        if ($diff === []) {
            return;
        }

        $actorId = auth()->id();

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventUpdated,
            'user_id' => is_int($actorId) ? $actorId : null,
            'reason' => 'Asset-Felder wurden aktualisiert.',
            'meta' => [
                'source' => $this->resolveSource(),
                'actor_type' => is_int($actorId) ? 'user' : 'system',
                'changes' => $diff,
            ],
        ]);
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

    private function resolveSource(): string
    {
        $contextSource = AssetAuditContext::source();
        if ($contextSource !== null && $contextSource !== '') {
            return $contextSource;
        }

        if (app()->runningInConsole()) {
            $argv = $_SERVER['argv'] ?? [];
            if (is_array($argv) && isset($argv[1]) && is_string($argv[1]) && $argv[1] !== '') {
                return 'console:'.$argv[1];
            }

            return 'console';
        }

        if (request()->route()?->getName()) {
            return 'route:'.request()->route()->getName();
        }

        $path = trim((string) request()->path(), '/');

        return $path !== '' ? 'request:'.$path : 'request';
    }
}
