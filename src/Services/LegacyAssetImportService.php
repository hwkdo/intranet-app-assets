<?php

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Models\Handover;

class LegacyAssetImportService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchLegacyAssets(IntranetLegacyService $legacyService): array
    {
        return $legacyService->getAssetsExportAlle();
    }

    /**
     * @param  array<int, array<string, mixed>>  $legacyAssets
     * @return array<int, int> map legacyAssetId => localAssetId
     */
    public function buildLegacyToLocalAssetMap(array $legacyAssets): array
    {
        $itexiaIds = array_values(array_filter(array_unique(array_map(
            static fn (array $legacy): string => trim((string) ($legacy['itexiaid'] ?? '')),
            $legacyAssets
        ))));

        $byItexia = $itexiaIds !== []
            ? Asset::query()->whereIn('itexia_id', $itexiaIds)->get()->keyBy('itexia_id')
            : collect();

        $legacyIds = array_values(array_unique(array_map(
            static fn (array $legacy): int => (int) ($legacy['id'] ?? 0),
            $legacyAssets
        )));
        $legacyIds = array_values(array_filter($legacyIds, static fn (int $id): bool => $id > 0));

        $byLegacy = $legacyIds !== []
            ? Asset::query()->whereIn('legacy_id', $legacyIds)->get()->keyBy('legacy_id')
            : collect();

        $result = [];
        foreach ($legacyAssets as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            $itexiaId = trim((string) ($legacy['itexiaid'] ?? ''));
            if ($itexiaId !== '' && $byItexia->has($itexiaId)) {
                $result[$legacyId] = (int) $byItexia->get($itexiaId)->id;

                continue;
            }

            if ($byLegacy->has($legacyId)) {
                $result[$legacyId] = (int) $byLegacy->get($legacyId)->id;
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $legacyAssets
     * @return array{imported: int, skipped: int, selected: int}
     */
    public function importMissingByLegacyIds(
        IntranetLegacyService $legacyService,
        array $legacyAssets,
        array $legacyIds
    ): array {
        $legacyIds = array_values(array_unique(array_filter(array_map('intval', $legacyIds), static fn (int $id): bool => $id > 0)));
        if ($legacyIds === []) {
            return ['imported' => 0, 'skipped' => 0, 'selected' => 0];
        }

        $this->syncAssetTypes($legacyService);
        $this->syncAssetVendors($legacyService);

        $selected = array_values(array_filter($legacyAssets, static function (array $legacy) use ($legacyIds): bool {
            return in_array((int) ($legacy['id'] ?? 0), $legacyIds, true);
        }));

        $existingMap = $this->buildLegacyToLocalAssetMap($selected);
        $toImport = array_values(array_filter($selected, static fn (array $legacy): bool => ! isset($existingMap[(int) ($legacy['id'] ?? 0)])));

        [$imported, $skipped] = $this->importAssets($toImport);
        $this->syncHandoversAndReturnsForAssets($legacyService, $toImport);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'selected' => count($selected),
        ];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function importAssets(array $legacyAssets): array
    {
        $typeMap = AssetType::pluck('id', 'legacy_id');
        $vendorMap = AssetVendor::pluck('id', 'legacy_id');
        $userMap = User::pluck('id', 'legacy_id');

        $imported = 0;
        $skipped = 0;

        foreach ($legacyAssets as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            $typeId = $typeMap[$legacy['assettyp_id'] ?? null] ?? null;
            $vendorId = $vendorMap[$legacy['assetvendor_id'] ?? null] ?? null;
            if (! $typeId || ! $vendorId) {
                $skipped++;
                continue;
            }

            $userId = null;
            $legacyUserId = $legacy['user_id'] ?? null;
            if ($legacyUserId !== null) {
                $userId = $userMap[$legacyUserId] ?? User::firstWhere('legacy_id', $legacyUserId)?->id;
            }

            $itexiaId = $legacy['itexiaid'] ?? null;
            $attributes = [
                'legacy_id' => $legacyId,
                'serial_number' => $legacy['sn'] ?? '',
                'model' => $legacy['modell'] ?? '',
                'asset_type_id' => $typeId,
                'asset_vendor_id' => $vendorId,
                'user_id' => $userId,
                'name' => $legacy['name'] ?? null,
                'location' => $legacy['standort'] ?? null,
                'is_clarification' => (bool) ($legacy['klaerung'] ?? false),
                'is_missing' => (bool) ($legacy['vermisst'] ?? false),
                'itexia_id' => $itexiaId,
                'order_number' => $legacy['ben'] ?? null,
                'invoice_number' => $legacy['rechnungsnr'] ?? null,
                'domain_connection' => $legacy['domain_connection'] ?? null,
                'domain_last_seen' => $this->parseNullableDatetime($legacy['domain_last_seen'] ?? null),
                'domain_last_checked' => $this->parseNullableDatetime($legacy['domain_last_checked'] ?? null),
                'last_logon' => $this->parseNullableDatetime($legacy['lastlogon'] ?? null),
                'last_logon_timestamp' => $this->parseNullableDatetime($legacy['lastlogontimestamp'] ?? null),
            ];

            $asset = $this->findOrCreateAsset($legacyId, $itexiaId, $attributes);
            $historyCreatedAt = isset($legacy['updated_at']) ? $legacy['updated_at'] : $asset->updated_at;
            $asset->historyEntries()->create([
                'event' => AssetHistory::EventUpdated,
                'user_id' => $userId,
                'created_at' => $historyCreatedAt,
                'updated_at' => $historyCreatedAt,
                'meta' => ['source' => 'legacy-assets-ui-import'],
            ]);

            $imported++;
        }

        return [$imported, $skipped];
    }

    /**
     * @param  array<int, array<string, mixed>>  $legacyAssets
     */
    private function syncHandoversAndReturnsForAssets(IntranetLegacyService $legacyService, array $legacyAssets): void
    {
        if ($legacyAssets === []) {
            return;
        }

        $assetIdMap = $this->buildLegacyIdToAssetIdMap($legacyAssets);
        if ($assetIdMap === []) {
            return;
        }

        $legacyAssetIdSet = array_flip(array_keys($assetIdMap));

        $legacyHandovers = $legacyService->getAssetHandoversExport();
        $handoverIdMap = [];

        foreach ($legacyHandovers as $legacyHandover) {
            $legacyHandoverId = $legacyHandover['id'] ?? null;
            $legacyAssetId = $legacyHandover['asset_id'] ?? null;
            if ($legacyHandoverId === null || $legacyAssetId === null) {
                continue;
            }
            if (! isset($legacyAssetIdSet[(int) $legacyAssetId])) {
                continue;
            }

            $newAssetId = $assetIdMap[(int) $legacyAssetId] ?? null;
            if (! $newAssetId) {
                continue;
            }

            $recipientId = $this->resolveUserId($legacyHandover['user_id'] ?? null);
            $issuerId = $this->resolveUserId($legacyHandover['admin_id'] ?? null);

            $hasFormwerk = ! empty($legacyHandover['formwerk_uebergabe'] ?? null);
            $hasSignature = ! empty($legacyHandover['unterschrift'] ?? null);
            $confirmedAt = null;
            $confirmationMethod = null;
            if ($hasFormwerk) {
                $confirmedAt = $this->parseNullableDatetime($legacyHandover['created_at'] ?? null) ?? now()->format('Y-m-d H:i:s');
                $confirmationMethod = 'formwerk';
            } elseif ($hasSignature) {
                $confirmedAt = $this->parseNullableDatetime($legacyHandover['created_at'] ?? null) ?? now()->format('Y-m-d H:i:s');
                $confirmationMethod = 'signopad';
            }

            $attributes = [
                'asset_id' => $newAssetId,
                'recipient_user_id' => $recipientId,
                'issuer_user_id' => $issuerId,
                'signature' => $legacyHandover['unterschrift'] ?? null,
                'file' => $legacyHandover['file'] ?? null,
                'formwerk_handover' => $legacyHandover['formwerk_uebergabe'] ?? null,
                'formwerk_return' => $legacyHandover['formwerk_rueckgabe'] ?? null,
            ];
            if ($confirmedAt !== null) {
                $attributes['confirmed_at'] = $confirmedAt;
                $attributes['confirmation_method'] = $confirmationMethod;
            }

            $handover = Handover::updateOrCreate(['legacy_id' => $legacyHandoverId], $attributes);
            if (isset($legacyHandover['created_at'])) {
                $handover->created_at = $legacyHandover['created_at'];
                $handover->save();
            }
            $handoverIdMap[(int) $legacyHandoverId] = (int) $handover->id;
        }

        $this->ensureOwnerHandoversFromAssets($legacyAssets);

        $legacyReturns = $legacyService->getAssetReturnsExport();
        foreach ($legacyReturns as $legacyReturn) {
            $legacyReturnId = $legacyReturn['id'] ?? null;
            $legacyHandoverId = $legacyReturn['uebergabe_id'] ?? null;
            if ($legacyReturnId === null || $legacyHandoverId === null) {
                continue;
            }

            $newHandoverId = $handoverIdMap[(int) $legacyHandoverId] ?? null;
            if (! $newHandoverId) {
                continue;
            }

            $recipientId = $this->resolveUserId($legacyReturn['ruecknehmer_id'] ?? null);
            $assetReturn = AssetReturn::updateOrCreate(
                ['legacy_id' => $legacyReturnId],
                ['handover_id' => $newHandoverId, 'recipient_user_id' => $recipientId]
            );

            if (isset($legacyReturn['created_at'])) {
                $assetReturn->created_at = $legacyReturn['created_at'];
            }
            if ($recipientId !== null) {
                $ts = $assetReturn->created_at ?? now();
                $assetReturn->received_confirmed_at = $ts;
                $assetReturn->completed_at = $ts;
            }
            $assetReturn->save();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $legacyAssets
     * @return array<int, int>
     */
    private function buildLegacyIdToAssetIdMap(array $legacyAssets): array
    {
        $itexiaIds = array_unique(array_filter(array_column($legacyAssets, 'itexiaid')));
        $byItexia = $itexiaIds !== [] ? Asset::whereIn('itexia_id', $itexiaIds)->get()->keyBy('itexia_id') : collect();

        $map = [];
        foreach ($legacyAssets as $legacy) {
            $legacyId = $legacy['id'] ?? null;
            if ($legacyId === null) {
                continue;
            }
            $itexiaId = $legacy['itexiaid'] ?? null;
            if ($itexiaId !== null && isset($byItexia[$itexiaId])) {
                $map[(int) $legacyId] = (int) $byItexia[$itexiaId]->id;
            } else {
                $asset = Asset::firstWhere('legacy_id', $legacyId);
                if ($asset !== null) {
                    $map[(int) $legacyId] = (int) $asset->id;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $legacyAssets
     */
    private function ensureOwnerHandoversFromAssets(array $legacyAssets): void
    {
        $assetIdMap = $this->buildLegacyIdToAssetIdMap($legacyAssets);

        foreach ($legacyAssets as $legacy) {
            $legacyAssetId = $legacy['id'] ?? null;
            if ($legacyAssetId === null) {
                continue;
            }

            $assetId = $assetIdMap[(int) $legacyAssetId] ?? null;
            if ($assetId === null) {
                continue;
            }

            $ownerUserId = $this->resolveUserId($legacy['user_id'] ?? null);
            if ($ownerUserId === null) {
                continue;
            }

            $matchingHandover = Handover::query()
                ->where('asset_id', $assetId)
                ->where('recipient_user_id', $ownerUserId)
                ->whereNull('rejected_at')
                ->orderByDesc('legacy_id')
                ->orderByDesc('created_at')
                ->first();

            if ($matchingHandover === null) {
                Handover::create([
                    'asset_id' => $assetId,
                    'recipient_user_id' => $ownerUserId,
                    'issuer_user_id' => null,
                    'confirmed_at' => null,
                    'confirmation_method' => null,
                ]);

                continue;
            }

            if ($matchingHandover->confirmed_at !== null) {
                continue;
            }

            $matchingHandover->update([
                'confirmed_at' => $this->parseNullableDatetime($legacy['updated_at'] ?? null)
                    ?? $this->parseNullableDatetime($legacy['created_at'] ?? null)
                    ?? now()->format('Y-m-d H:i:s'),
                'confirmation_method' => $matchingHandover->confirmation_method ?: 'legacy-import',
            ]);
        }
    }

    private function syncAssetTypes(IntranetLegacyService $legacyService): void
    {
        foreach ($legacyService->getAssetTypsAlle() as $legacy) {
            $legacyId = $legacy['id'] ?? null;
            if ($legacyId === null) {
                continue;
            }

            AssetType::updateOrCreate(
                ['legacy_id' => $legacyId],
                [
                    'name' => $legacy['name'] ?? '',
                    'is_domain_object' => (bool) ($legacy['domainobject'] ?? false),
                ]
            );
        }
    }

    private function syncAssetVendors(IntranetLegacyService $legacyService): void
    {
        foreach ($legacyService->getAssetVendorsAlle() as $legacy) {
            $legacyId = $legacy['id'] ?? null;
            if ($legacyId === null) {
                continue;
            }

            AssetVendor::updateOrCreate(
                ['legacy_id' => $legacyId],
                ['name' => $legacy['name'] ?? '']
            );
        }
    }

    private function findOrCreateAsset(int $legacyId, ?string $itexiaId, array $attributes): Asset
    {
        if ($itexiaId !== null) {
            $asset = Asset::firstWhere('itexia_id', $itexiaId);
            if ($asset !== null) {
                $asset->update($attributes);

                return $asset;
            }
        }

        $asset = Asset::firstWhere('legacy_id', $legacyId);
        if ($asset !== null) {
            $asset->update($attributes);

            return $asset;
        }

        return Asset::create($attributes);
    }

    private function resolveUserId(mixed $legacyUserId): ?int
    {
        if ($legacyUserId === null || $legacyUserId === '') {
            return null;
        }

        $id = is_numeric($legacyUserId) ? (int) $legacyUserId : null;
        if ($id === null) {
            return null;
        }

        $user = User::firstWhere('legacy_id', $id);

        return $user?->id;
    }

    private function parseNullableDatetime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            $year = (int) $value->format('Y');
            if ($year < 1000 || $year > 9999) {
                return null;
            }

            return $value->format('Y-m-d H:i:s');
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $str)
            ?: \DateTime::createFromFormat(\DateTime::ATOM, $str)
            ?: @\DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $str);

        if ($dt === false) {
            try {
                $dt = new \DateTime($str);
            } catch (\Exception) {
                return null;
            }
        }

        $year = (int) $dt->format('Y');
        if ($year < 1000 || $year > 9999) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }
}
