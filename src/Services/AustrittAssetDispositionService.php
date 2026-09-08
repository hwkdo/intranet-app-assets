<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\Standort;
use App\Models\User;
use Carbon\CarbonInterface;
use Hwkdo\IntranetAppAssets\Enums\AssetReturnSource;
use Hwkdo\IntranetAppAssets\Enums\ReturnScheduleType;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Models\ScheduledOwnerAssignment;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Hwkdo\IntranetAppAssets\Support\ReturnInitiatableHandoverResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Wendet Per-Asset-Dispositionen aus dem ma_austritt-Workflow an.
 * Nutzt bestehende Observer (Handover-Automation, Standort-Invarianten, AssetStockState).
 */
class AustrittAssetDispositionService
{
    public function __construct(
        private HandoverSupersessionService $handoverSupersession,
        private ReturnInitiatableHandoverResolver $returnHandoverResolver,
    ) {}

    /**
     * Asset verbleibt am Arbeitsplatz (ohne Besitzer, nicht auf Lager).
     */
    public function verbleibtArbeitsplatz(Asset $asset, string|int $standortOrLocation): void
    {
        $location = $this->resolveLocation($standortOrLocation);

        AssetAuditContext::runWith('assets.austritt.verbleibt_arbeitsplatz', function () use ($asset, $location): void {
            DB::transaction(function () use ($asset, $location): void {
                $this->handoverSupersession->supersedeAllActiveForAsset(
                    $asset,
                    $this->actorUserId(),
                    'austritt:verbleibt_arbeitsplatz',
                );

                $formerUserId = $asset->user_id;

                $asset->update([
                    'user_id' => null,
                    'location' => $location,
                    'is_in_stock' => false,
                    'is_missing' => false,
                    'is_clarification' => false,
                ]);

                $this->recordHistory(
                    $asset,
                    'Austritt: Asset verbleibt am Arbeitsplatz (Standort gesetzt, Besitzer entfernt).',
                    [
                        'choice' => 'verbleibt_arbeitsplatz',
                        'location' => $location,
                        'former_user_id' => $formerUserId,
                    ],
                );
            });
        });
    }

    /**
     * Geplante Rückgabe an die IT; Overdue-Reminder ab Cutoff an den VG.
     */
    public function anIt(
        Asset $asset,
        CarbonInterface $scheduledAt,
        int $vgUserId,
        CarbonInterface $austrittCutoffDate,
    ): AssetReturn {
        if ($asset->user_id === null) {
            throw new InvalidArgumentException('Asset hat keinen Besitzer – Rückgabe an IT nicht möglich.');
        }

        if ($vgUserId < 1 || ! User::query()->whereKey($vgUserId)->exists()) {
            throw new InvalidArgumentException('Vorgesetzter für Overdue-Benachrichtigung ungültig.');
        }

        return AssetAuditContext::runWith('assets.austritt.an_it', function () use ($asset, $scheduledAt, $vgUserId, $austrittCutoffDate): AssetReturn {
            return DB::transaction(function () use ($asset, $scheduledAt, $vgUserId, $austrittCutoffDate): AssetReturn {
                $handover = $this->resolveOrCreateConfirmedHandoverForReturn($asset);

                $existingOpen = $handover->assetReturns()->whereNull('completed_at')->first();
                if ($existingOpen !== null) {
                    $existingOpen->forceFill([
                        'schedule_type' => ReturnScheduleType::Scheduled,
                        'scheduled_at' => Carbon::instance($scheduledAt),
                        'source' => AssetReturnSource::Holder,
                        'overdue_notify_user_id' => $vgUserId,
                        'austritt_cutoff_date' => Carbon::instance($austrittCutoffDate)->toDateString(),
                    ])->save();

                    $this->recordHistory(
                        $asset,
                        'Austritt: Bestehende offene Rückgabe als geplante IT-Rückgabe aktualisiert.',
                        [
                            'choice' => 'an_it',
                            'asset_return_id' => $existingOpen->id,
                            'handover_id' => $handover->id,
                            'scheduled_at' => Carbon::instance($scheduledAt)->toIso8601String(),
                            'overdue_notify_user_id' => $vgUserId,
                            'austritt_cutoff_date' => Carbon::instance($austrittCutoffDate)->toDateString(),
                        ],
                    );

                    return $existingOpen->fresh() ?? $existingOpen;
                }

                $return = AssetReturn::query()->create([
                    'handover_id' => $handover->id,
                    'initiated_by_user_id' => $this->actorUserId() ?? $vgUserId,
                    'schedule_type' => ReturnScheduleType::Scheduled,
                    'scheduled_at' => Carbon::instance($scheduledAt),
                    'source' => AssetReturnSource::Holder,
                    'overdue_notify_user_id' => $vgUserId,
                    'austritt_cutoff_date' => Carbon::instance($austrittCutoffDate)->toDateString(),
                ]);

                $this->recordHistory(
                    $asset,
                    'Austritt: Geplante Rückgabe an IT eingeleitet für '
                        .Carbon::instance($scheduledAt)->format('d.m.Y H:i').'.',
                    [
                        'choice' => 'an_it',
                        'asset_return_id' => $return->id,
                        'handover_id' => $handover->id,
                        'scheduled_at' => Carbon::instance($scheduledAt)->toIso8601String(),
                        'overdue_notify_user_id' => $vgUserId,
                        'austritt_cutoff_date' => Carbon::instance($austrittCutoffDate)->toDateString(),
                    ],
                );

                return $return;
            });
        });
    }

    /**
     * Geplanter Besitzerwechsel auf den VG (wird vom Scheduler ausgeführt).
     */
    public function werdeIchErhalten(
        Asset $asset,
        int $vgUserId,
        CarbonInterface $executeAt,
        ?int $flowId = null,
    ): ScheduledOwnerAssignment {
        if ($vgUserId < 1 || ! User::query()->whereKey($vgUserId)->exists()) {
            throw new InvalidArgumentException('Neuer Besitzer (VG) ungültig.');
        }

        return AssetAuditContext::runWith('assets.austritt.werde_ich_erhalten', function () use ($asset, $vgUserId, $executeAt, $flowId): ScheduledOwnerAssignment {
            return DB::transaction(function () use ($asset, $vgUserId, $executeAt, $flowId): ScheduledOwnerAssignment {
                $assignment = ScheduledOwnerAssignment::query()->create([
                    'asset_id' => $asset->id,
                    'new_user_id' => $vgUserId,
                    'execute_at' => Carbon::instance($executeAt),
                    'flow_id' => $flowId,
                ]);

                $this->recordHistory(
                    $asset,
                    'Austritt: Geplanter Besitzerwechsel auf VG für '
                        .Carbon::instance($executeAt)->format('d.m.Y H:i').' vorgemerkt.',
                    [
                        'choice' => 'werde_ich_erhalten',
                        'scheduled_owner_assignment_id' => $assignment->id,
                        'new_user_id' => $vgUserId,
                        'execute_at' => Carbon::instance($executeAt)->toIso8601String(),
                        'flow_id' => $flowId,
                    ],
                );

                return $assignment;
            });
        });
    }

    /**
     * Sofort: Besitzer = VG (löst Handover-Automation aus).
     */
    public function habeIchErhalten(Asset $asset, int $vgUserId): void
    {
        $this->assignOwner($asset, $vgUserId, 'habe_ich_erhalten', 'Austritt: Asset an Vorgesetzten übergeben (sofort).');
    }

    /**
     * Besitzerwechsel auf einen anderen Mitarbeiter.
     */
    public function vonAnderem(Asset $asset, int $newUserId): void
    {
        $this->assignOwner($asset, $newUserId, 'von_anderem', 'Austritt: Asset an anderen Mitarbeiter übergeben.');
    }

    /**
     * Als vermisst markieren (wie Klärungs-Auflösung mark_missing).
     */
    public function vermisst(Asset $asset): void
    {
        AssetAuditContext::runWith('assets.austritt.vermisst', function () use ($asset): void {
            DB::transaction(function () use ($asset): void {
                $this->handoverSupersession->supersedeAllActiveForAsset(
                    $asset,
                    $this->actorUserId(),
                    'austritt:vermisst',
                );

                $formerUserId = $asset->user_id;

                $asset->update([
                    'user_id' => null,
                    'is_clarification' => false,
                    'is_missing' => true,
                ]);

                $this->recordHistory(
                    $asset,
                    'Austritt: Asset als vermisst markiert, Besitzer entfernt.',
                    [
                        'choice' => 'vermisst',
                        'former_user_id' => $formerUserId,
                    ],
                );
            });
        });
    }

    /**
     * Wendet Stichtag-Dispositionen an (ohne habe_ich_erhalten).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<string, mixed>>  $pending  assetId => row
     * @return list<string>
     */
    public function applyStichtagDispositions(array $payload, array $pending): array
    {
        $vgUserId = (int) ($payload['vorgesetzter_user_id'] ?? $payload['step2_actor_user_id'] ?? 0);
        $austrittRaw = $payload['austrittsdatum'] ?? null;
        $austrittCutoff = $austrittRaw
            ? Carbon::parse((string) $austrittRaw)->startOfDay()
            : now()->startOfDay();
        $flowId = isset($payload['flow_id']) && is_numeric($payload['flow_id'])
            ? (int) $payload['flow_id']
            : null;

        $messages = [];

        foreach ($pending as $assetId => $row) {
            $asset = Asset::query()->find((int) $assetId);
            if (! $asset) {
                throw new InvalidArgumentException("Asset #{$assetId} nicht gefunden");
            }

            $choice = (string) ($row['choice'] ?? '');

            match ($choice) {
                'verbleibt_arbeitsplatz' => $this->verbleibtArbeitsplatz(
                    $asset,
                    $row['standort_id'] ?? $row['location'] ?? '',
                ),
                'an_it' => $this->anIt(
                    $asset,
                    Carbon::parse((string) ($row['datetime'] ?? now())),
                    $vgUserId > 0 ? $vgUserId : throw new InvalidArgumentException('VG fehlt für an_it'),
                    $austrittCutoff,
                ),
                'werde_ich_erhalten' => $this->werdeIchErhalten(
                    $asset,
                    $vgUserId > 0 ? $vgUserId : throw new InvalidArgumentException('VG fehlt für werde_ich_erhalten'),
                    Carbon::parse((string) ($row['datetime'] ?? now())),
                    $flowId,
                ),
                'von_anderem' => $this->vonAnderem(
                    $asset,
                    (int) ($row['to_user_id'] ?? $row['user_id'] ?? 0),
                ),
                'vermisst' => $this->vermisst($asset),
                default => throw new InvalidArgumentException("Unbekannte Choice [{$choice}] für Asset #{$assetId}"),
            };

            $messages[] = "Asset #{$asset->id}: {$choice}";
        }

        return $messages;
    }

    private function assignOwner(Asset $asset, int $newUserId, string $choice, string $reason): void
    {
        if ($newUserId < 1 || ! User::query()->whereKey($newUserId)->exists()) {
            throw new InvalidArgumentException('Neuer Besitzer ungültig.');
        }

        AssetAuditContext::runWith('assets.austritt.'.$choice, function () use ($asset, $newUserId, $choice, $reason): void {
            DB::transaction(function () use ($asset, $newUserId, $choice, $reason): void {
                $this->handoverSupersession->supersedeAllActiveForAsset(
                    $asset,
                    $this->actorUserId(),
                    'austritt:'.$choice,
                );

                $formerUserId = $asset->user_id;

                $asset->update([
                    'user_id' => $newUserId,
                    'is_missing' => false,
                    'is_clarification' => false,
                    'is_in_stock' => false,
                ]);

                $this->recordHistory(
                    $asset,
                    $reason,
                    [
                        'choice' => $choice,
                        'former_user_id' => $formerUserId,
                        'new_user_id' => $newUserId,
                    ],
                );
            });
        });
    }

    private function resolveOrCreateConfirmedHandoverForReturn(Asset $asset): Handover
    {
        $handover = $this->returnHandoverResolver->forAsset($asset);
        if ($handover instanceof Handover) {
            return $handover;
        }

        // Bestätigte Übergabe ohne offene Rückgabe (auch wenn Resolver wegen offener Parallel-Übergabe blockiert)
        $confirmed = Handover::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->whereNotNull('confirmed_at')
            ->whereNull('rejected_at')
            ->where('recipient_user_id', $asset->user_id)
            ->whereDoesntHave('assetReturns', static fn ($query) => $query->whereNull('completed_at'))
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->first();

        if ($confirmed instanceof Handover) {
            return $confirmed;
        }

        // Offene Übergabe an aktuellen Besitzer: für Austritt-Pfad bestätigen
        $open = Handover::query()
            ->where('asset_id', $asset->id)
            ->open()
            ->where('recipient_user_id', $asset->user_id)
            ->orderByDesc('id')
            ->first();

        if ($open instanceof Handover) {
            $open->update([
                'confirmed_at' => now(),
                'confirmation_method' => 'austritt',
                'pending_confirmation_channel' => null,
            ]);

            return $open->fresh() ?? $open;
        }

        // Andere aktive Übergaben beenden und minimale bestätigte Übergabe anlegen
        $this->handoverSupersession->supersedeAllActiveForAsset(
            $asset,
            $this->actorUserId(),
            'austritt:an_it_minimal_handover',
        );

        return Handover::query()->create([
            'asset_id' => $asset->id,
            'recipient_user_id' => $asset->user_id,
            'issuer_user_id' => $this->actorUserId(),
            'confirmed_at' => now(),
            'confirmation_method' => 'austritt',
        ]);
    }

    private function resolveLocation(string|int $standortOrLocation): string
    {
        if (is_int($standortOrLocation) || (is_string($standortOrLocation) && ctype_digit($standortOrLocation))) {
            $standort = Standort::query()->find((int) $standortOrLocation);
            if ($standort === null || ! filled($standort->name)) {
                throw new InvalidArgumentException('Standort nicht gefunden.');
            }

            return (string) $standort->name;
        }

        $location = trim($standortOrLocation);
        if ($location === '') {
            throw new InvalidArgumentException('Standort erforderlich.');
        }

        return $location;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function recordHistory(Asset $asset, string $reason, array $meta): void
    {
        $asset->historyEntries()->create([
            'event' => AssetHistory::EventAustrittDisposition,
            'user_id' => $this->actorUserId(),
            'reason' => $reason,
            'meta' => array_merge(['source' => 'ma_austritt'], $meta),
        ]);
    }

    private function actorUserId(): ?int
    {
        $id = auth()->id();

        return is_int($id) ? $id : null;
    }
}
