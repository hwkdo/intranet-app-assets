<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Enums\ItexiaPictureSyncOutcome;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\SeventhingsLaravel\Models\Asset as SeventhingsAsset;
use Hwkdo\SeventhingsLaravel\SeventhingsLaravel;
use Illuminate\Support\Facades\Log;
use Throwable;

class ItexiaAssetMediaSyncService
{
    /**
     * Synchronisiert Bilder zwischen lokalem Asset und Itexia:
     * - lokal fehlt + API hat Bild => pull
     * - lokal hat Bild + API fehlt Bild => push
     */
    public function syncBidirectional(
        Asset $asset,
        SeventhingsLaravel $client,
        SeventhingsAsset $seventhingsAsset
    ): ItexiaPictureSyncOutcome {
        $localImage = $asset->getFirstMedia('image');
        $fileUuid = $this->firstPictureFileUuid($seventhingsAsset);

        if ($localImage !== null && $fileUuid !== null) {
            return ItexiaPictureSyncOutcome::SkippedBothHaveImage;
        }

        if ($localImage === null && $fileUuid === null) {
            return ItexiaPictureSyncOutcome::SkippedBothMissingImage;
        }

        if ($localImage === null && $fileUuid !== null) {
            return $this->pullFromItexia($asset, $client, $seventhingsAsset, $fileUuid);
        }

        /** @var \Spatie\MediaLibrary\MediaCollections\Models\Media $localImage */
        return $this->pushToItexia($asset, $client, $localImage, $seventhingsAsset);
    }

    private function pullFromItexia(
        Asset $asset,
        SeventhingsLaravel $client,
        SeventhingsAsset $seventhingsAsset,
        string $fileUuid
    ): ItexiaPictureSyncOutcome {
        $meta = $this->firstPictureMeta($seventhingsAsset);
        $filename = $this->sanitizeFilename($meta['name'] ?? null, $fileUuid);
        $mime = $meta['type'] ?? null;

        try {
            $full = $client->downloadFileData($fileUuid);
            if ($full === '') {
                Log::warning('intranet-app-assets: Itexia-Bild leer', ['asset_id' => $asset->id, 'file_uuid' => $fileUuid]);

                return ItexiaPictureSyncOutcome::Failed;
            }

            $adder = $asset->addMediaFromString($full)->usingFileName($filename);
            if ($mime !== null && $mime !== '') {
                $adder->withProperties(['mime_type' => $mime]);
            }
            $adder->toMediaCollection('image');

            try {
                $thumb = $client->downloadFileThumbnail($fileUuid);
                if ($thumb !== '') {
                    $thumbFilename = $this->thumbnailFilename($filename);
                    $asset->addMediaFromString($thumb)
                        ->usingFileName($thumbFilename)
                        ->toMediaCollection('thumbnail');
                }
            } catch (Throwable $e) {
                if ($this->isRateLimited($e)) {
                    throw $e;
                }
                Log::notice('intranet-app-assets: Itexia-Thumbnail nicht übernommen', [
                    'asset_id' => $asset->id,
                    'file_uuid' => $fileUuid,
                    'message' => $e->getMessage(),
                ]);
            }

            Log::info('intranet-app-assets: Itexia-Bild übernommen', [
                'asset_id' => $asset->id,
                'file_uuid' => $fileUuid,
            ]);

            return ItexiaPictureSyncOutcome::PulledFromItexia;
        } catch (Throwable $e) {
            if ($this->isRateLimited($e)) {
                throw $e;
            }

            Log::warning('intranet-app-assets: Itexia-Bild-Download fehlgeschlagen', [
                'asset_id' => $asset->id,
                'file_uuid' => $fileUuid,
                'message' => $e->getMessage(),
            ]);

            return ItexiaPictureSyncOutcome::Failed;
        }
    }

    private function pushToItexia(
        Asset $asset,
        SeventhingsLaravel $client,
        \Spatie\MediaLibrary\MediaCollections\Models\Media $localImage,
        SeventhingsAsset $seventhingsAsset
    ): ItexiaPictureSyncOutcome {
        try {
            $objectUuid = $this->objectUuid($seventhingsAsset);
            if ($objectUuid === null) {
                Log::warning('intranet-app-assets: Itexia-Bild-Push ohne objectUuid', ['asset_id' => $asset->id]);

                return ItexiaPictureSyncOutcome::Failed;
            }

            $contents = file_get_contents($localImage->getPath());
            if (! is_string($contents) || $contents === '') {
                Log::warning('intranet-app-assets: Lokales Bild konnte nicht gelesen werden', ['asset_id' => $asset->id]);

                return ItexiaPictureSyncOutcome::Failed;
            }

            $newFileUuid = $client->uploadFile($contents, $localImage->file_name);
            $client->addFileToObject($objectUuid, 'picture', $newFileUuid);

            Log::info('intranet-app-assets: Lokales Bild nach Itexia gepusht', [
                'asset_id' => $asset->id,
                'object_uuid' => $objectUuid,
                'file_uuid' => $newFileUuid,
            ]);

            return ItexiaPictureSyncOutcome::PushedToItexia;
        } catch (Throwable $e) {
            if ($this->isRateLimited($e)) {
                throw $e;
            }

            Log::warning('intranet-app-assets: Itexia-Bild-Push fehlgeschlagen', [
                'asset_id' => $asset->id,
                'message' => $e->getMessage(),
            ]);

            return ItexiaPictureSyncOutcome::Failed;
        }
    }

    private function isRateLimited(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'too many requests')
            || str_contains($message, 'rate limit')
            || str_contains($message, '429')
            || str_contains($e->getMessage(), '420');
    }

    private function firstPictureFileUuid(SeventhingsAsset $seventhingsAsset): ?string
    {
        $row = $seventhingsAsset->getRawData();
        $picture = is_object($row) ? ($row->picture ?? null) : null;
        if (! is_array($picture) || $picture === []) {
            return null;
        }

        $first = $picture[0];
        $uuid = null;
        if (is_object($first)) {
            $uuid = $first->uuid ?? null;
        } elseif (is_array($first)) {
            $uuid = $first['uuid'] ?? null;
        }

        if ($uuid === null || $uuid === '') {
            return null;
        }

        return (string) $uuid;
    }

    /**
     * @return array{name: ?string, type: ?string}
     */
    private function firstPictureMeta(SeventhingsAsset $seventhingsAsset): array
    {
        $row = $seventhingsAsset->getRawData();
        $picture = is_object($row) ? ($row->picture ?? null) : null;
        if (! is_array($picture) || $picture === []) {
            return ['name' => null, 'type' => null];
        }

        $first = $picture[0];
        if (is_object($first)) {
            return [
                'name' => isset($first->name) ? (string) $first->name : null,
                'type' => isset($first->type) ? (string) $first->type : null,
            ];
        }
        if (is_array($first)) {
            return [
                'name' => isset($first['name']) ? (string) $first['name'] : null,
                'type' => isset($first['type']) ? (string) $first['type'] : null,
            ];
        }

        return ['name' => null, 'type' => null];
    }

    private function sanitizeFilename(?string $name, string $fileUuid): string
    {
        $base = $name !== null && $name !== '' ? basename($name) : 'itexia-'.$fileUuid.'.jpg';
        $base = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $base) ?? 'itexia-'.$fileUuid.'.jpg';
        if ($base === '' || $base === '.' || $base === '..') {
            $base = 'itexia-'.$fileUuid.'.jpg';
        }

        return $base;
    }

    private function thumbnailFilename(string $filename): string
    {
        $pathInfo = pathinfo($filename);

        return ($pathInfo['filename'] ?? 'thumb').'-thumb.'.($pathInfo['extension'] ?? 'jpg');
    }

    private function objectUuid(SeventhingsAsset $seventhingsAsset): ?string
    {
        $row = $seventhingsAsset->getRawData();
        $uuid = is_object($row) ? ($row->asset_uuid ?? null) : null;

        if (! is_string($uuid) || trim($uuid) === '') {
            return null;
        }

        return trim($uuid);
    }
}
