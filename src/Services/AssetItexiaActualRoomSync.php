<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Data\AssetItexiaRoomSyncResult;
use Hwkdo\IntranetAppAssets\Enums\AssetItexiaRoomSyncStatus;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Support\ItexiaRoomHintMatcher;
use Hwkdo\SeventhingsLaravel\Events\ItexiaAssetActualRoomUpdated;
use Hwkdo\SeventhingsLaravel\Models\Asset as ItexiaAsset;
use Hwkdo\SeventhingsLaravel\SeventhingsLaravel;
use Hwkdo\SeventhingsLaravel\Support\ItexiaRoomInspection;
use Hwkdo\SeventhingsLaravel\Support\SeventhingsObjectUuid;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Setzt den Itexia-Ist-Raum eines Assets aus dem Intranet-Standort.
 * Nur bei genau einem passenden Raum; Soll-Raum bleibt unverändert.
 */
class AssetItexiaActualRoomSync
{
    public function sync(Asset $asset): AssetItexiaRoomSyncResult
    {
        $seventhingsClass = SeventhingsLaravel::class;
        if (! class_exists($seventhingsClass) || ! app()->bound($seventhingsClass)) {
            return AssetItexiaRoomSyncResult::make(
                AssetItexiaRoomSyncStatus::Unavailable,
                'Seventhings ist nicht gebunden oder nicht verfügbar. Der Ist-Raum wurde nicht geändert.',
            );
        }

        $barcode = trim((string) $asset->itexia_id);

        /** @var SeventhingsLaravel $client */
        $client = app($seventhingsClass);

        try {
            $itexiaAsset = $client->findAsset($barcode);
        } catch (Throwable $e) {
            Log::warning('AssetItexiaActualRoomSync: findAsset fehlgeschlagen', [
                'asset_id' => $asset->id,
                'barcode' => $barcode,
                'exception' => $e->getMessage(),
            ]);

            return AssetItexiaRoomSyncResult::make(
                AssetItexiaRoomSyncStatus::ApiError,
                'Itexia-Abfrage fehlgeschlagen: '.$e->getMessage().' Der Ist-Raum wurde nicht geändert.',
            );
        }

        if (! $itexiaAsset instanceof ItexiaAsset) {
            return AssetItexiaRoomSyncResult::make(
                AssetItexiaRoomSyncStatus::NotFound,
                'Kein Itexia-Objekt mit diesem Barcode gefunden. Der Ist-Raum wurde nicht geändert.',
            );
        }

        $objectUuid = SeventhingsObjectUuid::fromItexiaAsset($itexiaAsset);
        if ($objectUuid === null || trim($objectUuid) === '') {
            return AssetItexiaRoomSyncResult::make(
                AssetItexiaRoomSyncStatus::MissingUuid,
                'Objekt-UUID konnte aus der Itexia-Antwort nicht ermittelt werden. Der Ist-Raum wurde nicht geändert.',
            );
        }

        $resolved = AssetItexiaRoomSearchHintResolver::resolve($asset);
        $hint = isset($resolved['hint']) ? trim((string) $resolved['hint']) : '';
        if ($hint === '') {
            return AssetItexiaRoomSyncResult::make(
                AssetItexiaRoomSyncStatus::NoHint,
                'Kein Standorttext für die Raumzuordnung (Besitzer-Raum und Pool-Standort sind leer). Der Ist-Raum wurde nicht geändert.',
            );
        }

        try {
            $rooms = $client->getRaeume();
        } catch (Throwable $e) {
            Log::warning('AssetItexiaActualRoomSync: getRaeume fehlgeschlagen', [
                'asset_id' => $asset->id,
                'exception' => $e->getMessage(),
            ]);

            return AssetItexiaRoomSyncResult::make(
                AssetItexiaRoomSyncStatus::ApiError,
                'Raumliste konnte nicht geladen werden: '.$e->getMessage().' Der Ist-Raum wurde nicht geändert.',
                $hint,
            );
        }

        $roomList = $rooms instanceof Collection ? $rooms : collect(is_array($rooms) ? $rooms : []);
        $matches = ItexiaRoomHintMatcher::findMatchingRooms($roomList, $hint);

        if ($matches === []) {
            return AssetItexiaRoomSyncResult::make(
                AssetItexiaRoomSyncStatus::NoRoomMatch,
                'Zum Standort „'.$hint.'“ wurde kein Itexia-Raum gefunden. Der Ist-Raum wurde nicht geändert.',
                $hint,
            );
        }

        if (count($matches) > 1) {
            return AssetItexiaRoomSyncResult::make(
                AssetItexiaRoomSyncStatus::Ambiguous,
                'Der Standort „'.$hint.'“ ist in Itexia nicht eindeutig. Gefundene Räume: '.$this->formatRoomList($matches).'. Der Ist-Raum wurde nicht geändert.',
                $hint,
                matchingRooms: $matches,
            );
        }

        $room = $matches[0];
        $roomLabel = $this->roomLabel($room);
        $currentRoomId = ItexiaRoomInspection::normalizeRoomIdFromItexiaAsset($itexiaAsset);

        if ($currentRoomId !== null && $currentRoomId === $room['id']) {
            return AssetItexiaRoomSyncResult::make(
                AssetItexiaRoomSyncStatus::AlreadySet,
                'Der Ist-Raum in Itexia ist bereits „'.$roomLabel.'“ (ID '.$room['id'].'). Eine manuelle Raumänderung ist nicht nötig.',
                $hint,
                $room['id'],
                $roomLabel,
                $matches,
            );
        }

        try {
            $client->updateAsset($objectUuid, ['actual_room' => $room['id']]);
        } catch (Throwable $e) {
            Log::error('AssetItexiaActualRoomSync: PATCH fehlgeschlagen', [
                'asset_id' => $asset->id,
                'exception' => $e->getMessage(),
            ]);

            return AssetItexiaRoomSyncResult::make(
                AssetItexiaRoomSyncStatus::PatchFailed,
                'Raum konnte nicht gesetzt werden: '.$e->getMessage().' Der Ist-Raum wurde nicht geändert.',
                $hint,
                $room['id'],
                $roomLabel,
                $matches,
            );
        }

        Event::dispatch(new ItexiaAssetActualRoomUpdated($objectUuid, $room['id'], $barcode !== '' ? $barcode : null));

        return AssetItexiaRoomSyncResult::make(
            AssetItexiaRoomSyncStatus::Applied,
            'Der Ist-Raum wurde in Itexia auf „'.$roomLabel.'“ (ID '.$room['id'].') gesetzt. Eine manuelle Raumänderung ist nicht nötig.',
            $hint,
            $room['id'],
            $roomLabel,
            $matches,
        );
    }

    /**
     * @param  array{id: int, name: string, label: string, nummer: string}  $room
     */
    private function roomLabel(array $room): string
    {
        $name = trim($room['name']);
        if ($name !== '') {
            return $name;
        }

        $label = trim($room['label']);
        if ($label !== '') {
            return $label;
        }

        $nummer = trim($room['nummer']);

        return $nummer !== '' ? $nummer : 'ID '.$room['id'];
    }

    /**
     * @param  list<array{id: int, name: string, label: string, nummer: string}>  $rooms
     */
    private function formatRoomList(array $rooms): string
    {
        $parts = [];
        foreach ($rooms as $room) {
            $parts[] = $this->roomLabel($room).' (ID '.$room['id'].')';
        }

        return implode('; ', $parts);
    }
}
