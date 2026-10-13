<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetNote;
use Illuminate\Support\Carbon;
use RuntimeException;

class BackfillYubikeyNotesService
{
    private const int CHUNK_SIZE = 80;

    private const int UNFILTERED_EXPORT_THRESHOLD = 1000;

    private const string LEGACY_ASSET_NOTE_TYPE = 'App\\Asset\\Asset';

    /**
     * @return array{
     *     candidates: int,
     *     created: int,
     *     already_present: int,
     *     assets_without_legacy_notes: int,
     *     assets_with_pin_text: int,
     *     rows: list<array{asset_id: int, legacy_id: int, serial_number: string, notes: int, has_pin_text: bool}>
     * }
     */
    public function run(IntranetLegacyService $legacyService, bool $dryRun, bool $withTrashed): array
    {
        $typeId = app(YubikeyOverviewService::class)->resolveYubikeyTypeId();
        if ($typeId === null) {
            throw new RuntimeException('Asset-Typ „Yubikey“ wurde nicht gefunden.');
        }

        $query = Asset::query()
            ->where('asset_type_id', $typeId)
            ->whereNotNull('legacy_id')
            ->whereDoesntHave('notes', function ($notes): void {
                $notes->whereRaw('LOWER(note) LIKE ?', ['%pin%']);
            });

        if ($withTrashed) {
            $query->withTrashed();
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Asset> $assets */
        $assets = $query->get(['id', 'legacy_id', 'serial_number']);

        $legacyAssetIds = $assets
            ->pluck('legacy_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $notesByAsset = $this->fetchAssetNotes($legacyService, $legacyAssetIds);
        $userMap = $this->userMapForNotes($notesByAsset);
        $existingLegacyNoteIds = AssetNote::query()
            ->whereNotNull('legacy_id')
            ->pluck('legacy_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->flip();

        $created = 0;
        $alreadyPresent = 0;
        $withoutLegacyNotes = 0;
        $assetsWithPinText = 0;
        $rows = [];

        foreach ($assets as $asset) {
            $legacyNotes = $notesByAsset[(int) $asset->legacy_id] ?? [];
            $importable = 0;
            $newCount = 0;
            $hasPinText = false;

            foreach ($legacyNotes as $note) {
                $legacyNoteId = (int) ($note['id'] ?? 0);
                $text = trim((string) ($note['bemerkung'] ?? ''));
                if ($legacyNoteId < 1 || $text === '') {
                    continue;
                }

                $importable++;
                if ($existingLegacyNoteIds->has($legacyNoteId)) {
                    $alreadyPresent++;

                    continue;
                }

                if (preg_match('/pin/i', $text) === 1) {
                    $hasPinText = true;
                }

                $newCount++;
                if (! $dryRun) {
                    $this->createNote($asset, $legacyNoteId, $text, $note, $userMap);
                }
                $existingLegacyNoteIds->put($legacyNoteId, true);
            }

            if ($importable === 0) {
                $withoutLegacyNotes++;

                continue;
            }

            if ($newCount === 0) {
                continue;
            }

            $created += $newCount;
            if ($hasPinText) {
                $assetsWithPinText++;
            }

            $rows[] = [
                'asset_id' => (int) $asset->id,
                'legacy_id' => (int) $asset->legacy_id,
                'serial_number' => (string) $asset->serial_number,
                'notes' => $newCount,
                'has_pin_text' => $hasPinText,
            ];
        }

        return [
            'candidates' => $assets->count(),
            'created' => $created,
            'already_present' => $alreadyPresent,
            'assets_without_legacy_notes' => $withoutLegacyNotes,
            'assets_with_pin_text' => $assetsWithPinText,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<int>  $legacyAssetIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchAssetNotes(IntranetLegacyService $legacyService, array $legacyAssetIds): array
    {
        $grouped = [];

        foreach (array_chunk($legacyAssetIds, self::CHUNK_SIZE) as $chunk) {
            $notes = $legacyService->getAssetNotesExportForAssetIds($chunk);
            $this->assertExportIsFiltered($notes, $chunk);

            foreach ($notes as $note) {
                if (! is_array($note)) {
                    continue;
                }
                if (($note['noteable_type'] ?? '') !== self::LEGACY_ASSET_NOTE_TYPE) {
                    continue;
                }

                $noteableId = (int) ($note['noteable_id'] ?? 0);
                if ($noteableId < 1) {
                    continue;
                }

                $grouped[$noteableId][] = $note;
            }
        }

        return $grouped;
    }

    /**
     * @param  array<int, array<string, mixed>>  $notes
     * @param  list<int>  $chunk
     */
    private function assertExportIsFiltered(array $notes, array $chunk): void
    {
        if (count($notes) < self::UNFILTERED_EXPORT_THRESHOLD) {
            return;
        }

        $wanted = array_flip($chunk);
        foreach ($notes as $note) {
            if (! is_array($note)) {
                continue;
            }
            $noteableId = (int) ($note['noteable_id'] ?? 0);
            if (isset($wanted[$noteableId]) && ($note['noteable_type'] ?? '') === self::LEGACY_ASSET_NOTE_TYPE) {
                return;
            }
        }

        throw new RuntimeException(
            'Die Legacy-API hat asset_ids ignoriert und einen ungefilterten Notiz-Export geliefert. Zuerst das Legacy-Intranet deployen, danach den Befehl erneut ausführen.'
        );
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $notesByAsset
     * @return \Illuminate\Support\Collection<int|string, int>
     */
    private function userMapForNotes(array $notesByAsset): \Illuminate\Support\Collection
    {
        $legacyUserIds = [];
        foreach ($notesByAsset as $notes) {
            foreach ($notes as $note) {
                $legacyUserId = $note['user_id'] ?? null;
                if (is_numeric($legacyUserId) && (int) $legacyUserId > 0) {
                    $legacyUserIds[] = (int) $legacyUserId;
                }
            }
        }

        if ($legacyUserIds === []) {
            return collect();
        }

        return User::query()
            ->whereIn('legacy_id', array_values(array_unique($legacyUserIds)))
            ->pluck('id', 'legacy_id');
    }

    /**
     * @param  array<string, mixed>  $note
     * @param  \Illuminate\Support\Collection<int|string, int>  $userMap
     */
    private function createNote(Asset $asset, int $legacyNoteId, string $text, array $note, \Illuminate\Support\Collection $userMap): void
    {
        $legacyUserId = $note['user_id'] ?? null;
        $userId = is_numeric($legacyUserId) ? $userMap->get((int) $legacyUserId) : null;

        $attributes = [
            'legacy_id' => $legacyNoteId,
            'noteable_type' => Asset::class,
            'noteable_id' => $asset->id,
            'note' => $text,
            'user_id' => $userId !== null ? (int) $userId : null,
        ];

        $createdAt = $this->parseCreatedAt($note['created_at'] ?? null);
        if ($createdAt !== null) {
            $attributes['created_at'] = $createdAt;
            $attributes['updated_at'] = $createdAt;
        }

        AssetNote::query()->create($attributes);
    }

    private function parseCreatedAt(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        $year = (int) $parsed->format('Y');
        if ($year < 1000 || $year > 9999) {
            return null;
        }

        return $parsed->format('Y-m-d H:i:s');
    }
}
