<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetAttachment;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetNote;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Console\Command;

class SyncLegacyAssetsCommand extends Command
{
    public $signature = 'intranet-app-assets:sync-legacy
                         {--dry-run : Nur anzeigen, was synchronisiert würde (keine Änderungen)}';

    public $description = 'Synchronisiert Assets aus dem Legacy-Intranet per HTTP (nur Assets mit itexiaid).';

    private bool $dryRun = false;

    public function handle(IntranetLegacyService $legacyService): int
    {
        if (! config('legacy.base_api_url') || ! config('legacy.base_api_token')) {
            $this->error('Legacy-API nicht konfiguriert: INTRANET_LEGACY_BASE_API_URL und INTRANET_LEGACY_API_TOKEN müssen gesetzt sein.');

            return self::FAILURE;
        } else {
            $this->info('Legacy-API '.config('legacy.base_api_url'));
        }

        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('DRY-RUN: Es werden keine Änderungen vorgenommen.');
        }

        $this->syncAssetTypes($legacyService);
        $this->syncAssetVendors($legacyService);
        $legacyAssets = $this->syncAssets($legacyService);
        $this->syncHandoversAndReturns($legacyService, $legacyAssets);
        $this->syncAttachments($legacyService, $legacyAssets);
        $this->syncAssetNotes($legacyService, $legacyAssets);

        $this->newLine();
        $this->info('Synchronisierung abgeschlossen.');

        return self::SUCCESS;
    }

    private function syncAssetTypes(IntranetLegacyService $legacyService): void
    {
        $this->line('Synchronisiere Asset-Typen…');

        $legacyTypes = $legacyService->getAssetTypsAlle();

        $count = 0;
        foreach ($legacyTypes as $legacy) {
            $legacyId = $legacy['id'] ?? null;
            if ($legacyId === null) {
                continue;
            }
            if (! $this->dryRun) {
                AssetType::updateOrCreate(
                    ['legacy_id' => $legacyId],
                    [
                        'name' => $legacy['name'] ?? '',
                        'is_domain_object' => (bool) ($legacy['domainobject'] ?? false),
                    ]
                );
            }
            $count++;
        }

        $this->line("  → {$count} Typen synchronisiert.");
    }

    private function syncAssetVendors(IntranetLegacyService $legacyService): void
    {
        $this->line('Synchronisiere Asset-Hersteller…');

        $legacyVendors = $legacyService->getAssetVendorsAlle();

        $count = 0;
        foreach ($legacyVendors as $legacy) {
            $legacyId = $legacy['id'] ?? null;
            if ($legacyId === null) {
                continue;
            }
            if (! $this->dryRun) {
                AssetVendor::updateOrCreate(
                    ['legacy_id' => $legacyId],
                    ['name' => $legacy['name'] ?? '']
                );
            }
            $count++;
        }

        $this->line("  → {$count} Hersteller synchronisiert.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function syncAssets(IntranetLegacyService $legacyService): array
    {
        $this->line('Synchronisiere Assets (nur mit itexiaid)…');

        $legacyAssets = $legacyService->getAssetsExportAlle();

        $this->line('  → '.count($legacyAssets).' Legacy-Assets mit itexiaid gefunden.');

        $typeMap = AssetType::pluck('id', 'legacy_id');
        $vendorMap = AssetVendor::pluck('id', 'legacy_id');
        $userMap = User::pluck('id', 'legacy_id');

        $synced = 0;
        $skipped = 0;

        foreach ($legacyAssets as $legacy) {
            $legacyId = $legacy['id'] ?? null;
            if ($legacyId === null) {
                continue;
            }

            $typeId = $typeMap[$legacy['assettyp_id'] ?? null] ?? null;
            $vendorId = $vendorMap[$legacy['assetvendor_id'] ?? null] ?? null;

            if (! $typeId || ! $vendorId) {
                $this->warn("  Asset ID {$legacyId} (itexiaid: ".($legacy['itexiaid'] ?? '').'): Typ oder Hersteller nicht gefunden – übersprungen.');
                $skipped++;

                continue;
            }

            $userId = null;
            $legacyUserId = $legacy['user_id'] ?? null;
            if ($legacyUserId !== null) {
                $userId = $userMap[$legacyUserId] ?? User::firstWhere('legacy_id', $legacyUserId)?->id;
            }

            if (! $this->dryRun) {
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
                ]);
            }
            $synced++;
        }

        $this->line("  → {$synced} Assets synchronisiert, {$skipped} übersprungen.");

        return $legacyAssets;
    }

    /**
     * @param  array<int, array<string, mixed>>  $legacyAssets
     */
    private function syncHandoversAndReturns(IntranetLegacyService $legacyService, array $legacyAssets): void
    {
        $this->line('Synchronisiere Übergaben und Rückgaben…');

        $assetIdMap = $this->buildLegacyIdToAssetIdMap($legacyAssets);

        $legacyHandovers = $legacyService->getAssetHandoversExport();
        $handoverIdMap = [];
        $synced = 0;

        foreach ($legacyHandovers as $legacyHandover) {
            $legacyHandoverId = $legacyHandover['id'] ?? null;
            $legacyAssetId = $legacyHandover['asset_id'] ?? null;
            if ($legacyHandoverId === null || $legacyAssetId === null) {
                continue;
            }

            $newAssetId = $assetIdMap[$legacyAssetId] ?? null;
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

            if (! $this->dryRun) {
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
                $handover = Handover::updateOrCreate(
                    ['legacy_id' => $legacyHandoverId],
                    $attributes
                );
                if (isset($legacyHandover['created_at'])) {
                    $handover->created_at = $legacyHandover['created_at'];
                    $handover->save();
                }
                $handoverIdMap[$legacyHandoverId] = $handover->id;
            }
            $synced++;
        }

        $this->line("  → {$synced} Übergaben synchronisiert.");
        $this->ensureOwnerHandoversFromAssets($legacyAssets);

        if ($this->dryRun) {
            return;
        }

        $legacyReturns = $legacyService->getAssetReturnsExport();
        $returnIdMap = [];
        $returnsSynced = 0;

        foreach ($legacyReturns as $legacyReturn) {
            $legacyReturnId = $legacyReturn['id'] ?? null;
            $legacyHandoverId = $legacyReturn['uebergabe_id'] ?? null;
            if ($legacyReturnId === null || $legacyHandoverId === null) {
                continue;
            }

            $newHandoverId = $handoverIdMap[$legacyHandoverId] ?? null;
            if (! $newHandoverId) {
                continue;
            }

            $recipientId = $this->resolveUserId($legacyReturn['ruecknehmer_id'] ?? null);

            $assetReturn = AssetReturn::updateOrCreate(
                ['legacy_id' => $legacyReturnId],
                [
                    'handover_id' => $newHandoverId,
                    'recipient_user_id' => $recipientId,
                ]
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
            $returnIdMap[$legacyReturnId] = $assetReturn->id;
            $returnsSynced++;
        }

        $this->line("  → {$returnsSynced} Rückgaben synchronisiert.");

        $this->syncHandoverNotes($legacyService, $handoverIdMap, $returnIdMap, $legacyReturns);
    }

    /**
     * Stellt sicher, dass für jedes importierte Asset mit Besitzer (user_id) mindestens
     * eine passende Übergabe existiert. Falls eine passende Übergabe vorhanden ist,
     * wird sie beim Import als bestätigt markiert.
     *
     * @param  array<int, array<string, mixed>>  $legacyAssets
     */
    private function ensureOwnerHandoversFromAssets(array $legacyAssets): void
    {
        $assetIdMap = $this->buildLegacyIdToAssetIdMap($legacyAssets);

        $created = 0;
        $confirmed = 0;

        foreach ($legacyAssets as $legacy) {
            $legacyAssetId = $legacy['id'] ?? null;
            if ($legacyAssetId === null) {
                continue;
            }

            $assetId = $assetIdMap[$legacyAssetId] ?? null;
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
                $created++;
                if (! $this->dryRun) {
                    Handover::create([
                        'asset_id' => $assetId,
                        'recipient_user_id' => $ownerUserId,
                        'issuer_user_id' => null,
                        'confirmed_at' => null,
                        'confirmation_method' => null,
                    ]);
                }

                continue;
            }

            if ($matchingHandover->confirmed_at !== null) {
                continue;
            }

            $confirmed++;
            if (! $this->dryRun) {
                $matchingHandover->update([
                    'confirmed_at' => $this->parseNullableDatetime($legacy['updated_at'] ?? null)
                        ?? $this->parseNullableDatetime($legacy['created_at'] ?? null)
                        ?? now()->format('Y-m-d H:i:s'),
                    'confirmation_method' => $matchingHandover->confirmation_method ?: 'legacy-import',
                ]);
            }
        }

        $this->line("  → {$created} Übergaben für Besitzer ergänzt, {$confirmed} passende Übergaben als bestätigt markiert.");
    }

    /**
     * @param  array<int, int>  $handoverIdMap
     * @param  array<int, int>  $returnIdMap
     * @param  array<int, array<string, mixed>>  $legacyReturns
     */
    private function syncHandoverNotes(
        IntranetLegacyService $legacyService,
        array $handoverIdMap,
        array $returnIdMap,
        array $legacyReturns
    ): void {
        $legacyReturnMap = [];
        foreach ($legacyReturns as $r) {
            $id = $r['id'] ?? null;
            if ($id !== null) {
                $legacyReturnMap[$id] = $r;
            }
        }

        $allNotes = $legacyService->getAssetNotesExport();
        $count = 0;

        foreach ($allNotes as $note) {
            $legacyNoteId = $note['id'] ?? null;
            $noteableType = $note['noteable_type'] ?? '';
            $noteableId = $note['noteable_id'] ?? null;
            if ($legacyNoteId === null || $noteableId === null) {
                continue;
            }

            $morphType = null;
            $morphId = null;

            if ($noteableType === 'App\\Asset\\Uebergabe') {
                $morphId = $handoverIdMap[$noteableId] ?? null;
                if ($morphId !== null) {
                    $morphType = Handover::class;
                }
            } elseif ($noteableType === 'App\\Asset\\Rueckgabe') {
                $legacyReturn = $legacyReturnMap[$noteableId] ?? null;
                if ($legacyReturn !== null) {
                    $newHandoverId = $handoverIdMap[$legacyReturn['uebergabe_id'] ?? null] ?? null;
                    $newReturnId = $newHandoverId !== null ? ($returnIdMap[$noteableId] ?? AssetReturn::where('handover_id', $newHandoverId)->value('id')) : null;
                    if ($newReturnId !== null) {
                        $morphType = AssetReturn::class;
                        $morphId = $newReturnId;
                    }
                }
            }

            if ($morphType === null || $morphId === null) {
                continue;
            }

            $userId = $this->resolveUserId($note['user_id'] ?? null);

            if (! $this->dryRun) {
                AssetNote::updateOrCreate(
                    ['legacy_id' => $legacyNoteId],
                    [
                        'noteable_type' => $morphType,
                        'noteable_id' => $morphId,
                        'note' => $note['bemerkung'] ?? '',
                        'user_id' => $userId,
                    ]
                );
                $noteModel = AssetNote::firstWhere('legacy_id', $legacyNoteId);
                if ($noteModel && isset($note['created_at'])) {
                    $noteModel->created_at = $note['created_at'];
                    $noteModel->save();
                }
            }
            $count++;
        }

        $this->line("  → {$count} Übergabe/Rückgabe-Notizen synchronisiert.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $legacyAssets
     */
    private function syncAttachments(IntranetLegacyService $legacyService, array $legacyAssets): void
    {
        $this->line('Synchronisiere Dateianhänge…');

        $assetIdMap = $this->buildLegacyIdToAssetIdMap($legacyAssets);

        $legacyAttachments = $legacyService->getAssetAttachmentsExport();
        $count = 0;

        foreach ($legacyAttachments as $attachment) {
            $legacyId = $attachment['id'] ?? null;
            $legacyAssetId = $attachment['asset_id'] ?? null;
            if ($legacyId === null || $legacyAssetId === null) {
                continue;
            }

            $newAssetId = $assetIdMap[$legacyAssetId] ?? null;
            if (! $newAssetId) {
                continue;
            }

            $userId = $this->resolveUserId($attachment['user_id'] ?? null);

            if (! $this->dryRun) {
                $attachmentModel = AssetAttachment::updateOrCreate(
                    ['legacy_id' => $legacyId],
                    [
                        'asset_id' => $newAssetId,
                        'file' => $attachment['file'] ?? '',
                        'user_id' => $userId,
                        'is_public' => (bool) ($attachment['public'] ?? true),
                    ]
                );
                if (isset($attachment['created_at'])) {
                    $attachmentModel->created_at = $attachment['created_at'];
                    $attachmentModel->save();
                }
            }
            $count++;
        }

        $this->line("  → {$count} Anhänge synchronisiert.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $legacyAssets
     */
    private function syncAssetNotes(IntranetLegacyService $legacyService, array $legacyAssets): void
    {
        $this->line('Synchronisiere Asset-Notizen…');

        $assetIdMap = $this->buildLegacyIdToAssetIdMap($legacyAssets);

        $allNotes = $legacyService->getAssetNotesExport();
        $count = 0;

        foreach ($allNotes as $note) {
            $noteableType = $note['noteable_type'] ?? '';
            $noteableId = $note['noteable_id'] ?? null;
            if ($noteableType !== 'App\\Asset\\Asset' || $noteableId === null) {
                continue;
            }

            $legacyNoteId = $note['id'] ?? null;
            if ($legacyNoteId === null) {
                continue;
            }

            $newAssetId = $assetIdMap[$noteableId] ?? null;
            if (! $newAssetId) {
                continue;
            }

            $userId = $this->resolveUserId($note['user_id'] ?? null);

            if (! $this->dryRun) {
                AssetNote::updateOrCreate(
                    ['legacy_id' => $legacyNoteId],
                    [
                        'noteable_type' => Asset::class,
                        'noteable_id' => $newAssetId,
                        'note' => $note['bemerkung'] ?? '',
                        'user_id' => $userId,
                    ]
                );
                $noteModel = AssetNote::firstWhere('legacy_id', $legacyNoteId);
                if ($noteModel && isset($note['created_at'])) {
                    $noteModel->created_at = $note['created_at'];
                    $noteModel->save();
                }
            }
            $count++;
        }

        $this->line("  → {$count} Asset-Notizen synchronisiert.");
    }

    /**
     * Findet ein Asset per itexia_id oder legacy_id und aktualisiert es, oder legt es an.
     * Vermeidet Duplikate bei beiden Unique-Keys (itexia_id, legacy_id).
     */
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

    /**
     * Map Legacy-Asset-ID → lokale Asset-ID (über itexia_id, da ein Asset nur einmal vorkommt).
     *
     * @param  array<int, array<string, mixed>>  $legacyAssets
     * @return array<int, int>
     */
    private function buildLegacyIdToAssetIdMap(array $legacyAssets): array
    {
        $itexiaIds = array_unique(array_filter(array_column($legacyAssets, 'itexiaid')));
        $byItexia = Asset::whereIn('itexia_id', $itexiaIds)->get()->keyBy('itexia_id');

        $map = [];
        foreach ($legacyAssets as $legacy) {
            $legacyId = $legacy['id'] ?? null;
            if ($legacyId === null) {
                continue;
            }
            $itexiaId = $legacy['itexiaid'] ?? null;
            if ($itexiaId !== null && isset($byItexia[$itexiaId])) {
                $map[$legacyId] = $byItexia[$itexiaId]->id;
            } else {
                $asset = Asset::firstWhere('legacy_id', $legacyId);
                if ($asset !== null) {
                    $map[$legacyId] = $asset->id;
                }
            }
        }

        return $map;
    }

    /**
     * Legacy-User-ID (int oder string aus JSON) in lokale User-ID umsetzen.
     */
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

    /**
     * Wandelt einen Legacy-Datumswert in ein für MySQL gültiges datetime um.
     * Ungültige Werte (z. B. Jahr < 1000, parse-Fehler) werden zu null.
     */
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
