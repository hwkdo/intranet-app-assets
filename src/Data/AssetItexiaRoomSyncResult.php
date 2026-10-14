<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Data;

use Hwkdo\IntranetAppAssets\Enums\AssetItexiaRoomSyncStatus;

readonly class AssetItexiaRoomSyncResult
{
    /**
     * @param  list<array{id: int, name: string, label: string, nummer: string}>  $matchingRooms
     */
    public function __construct(
        public AssetItexiaRoomSyncStatus $status,
        public string $summary,
        public ?string $hint = null,
        public ?int $roomId = null,
        public ?string $roomLabel = null,
        public array $matchingRooms = [],
    ) {}

    /**
     * @param  list<array{id: int, name: string, label: string, nummer: string}>  $matchingRooms
     */
    public static function make(
        AssetItexiaRoomSyncStatus $status,
        string $summary,
        ?string $hint = null,
        ?int $roomId = null,
        ?string $roomLabel = null,
        array $matchingRooms = [],
    ): self {
        return new self($status, $summary, $hint, $roomId, $roomLabel, $matchingRooms);
    }
}
