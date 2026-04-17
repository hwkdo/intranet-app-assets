<?php

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\SeventhingsLaravel\Models\Raum as ItexiaRaum;
use Hwkdo\SeventhingsLaravel\Support\ItexiaActualRoomResolver;
use Illuminate\Support\Collection;

/**
 * Sucht passende Seventhings-Räume zu einem Hint (Standort / Raumname).
 * Entspricht der Match-Logik von {@see ItexiaActualRoomResolver},
 * arbeitet aber mit einer bereits geladenen Raumliste (ein GET rooms für den gesamten Lauf).
 */
final class ItexiaRoomHintMatcher
{
    /**
     * @param  Collection<int, ItexiaRaum>  $rooms
     * @return list<array{id: int, name: string, label: string, nummer: string}>
     */
    public static function findMatchingRooms(Collection $rooms, string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $searchLower = mb_strtolower($search);
        $searchCompact = self::compactForRoomMatch($search);

        $out = [];
        foreach ($rooms as $room) {
            $name = (string) ($room->name ?? '');
            $label = (string) ($room->label ?? '');
            $nummer = (string) ($room->nummer ?? '');
            if (! self::matchesRoomFields($searchLower, $searchCompact, $name, $label, $nummer)) {
                continue;
            }

            $out[] = [
                'id' => (int) $room->id,
                'name' => $name,
                'label' => $label,
                'nummer' => $nummer,
            ];
        }

        return $out;
    }

    private static function compactForRoomMatch(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace('/\s+/u', '', $value) ?? '';
    }

    private static function matchesRoomFields(
        string $searchLower,
        string $searchCompact,
        string $name,
        string $label,
        string $nummer,
    ): bool {
        $nameLower = mb_strtolower(trim($name));
        $labelLower = mb_strtolower(trim($label));
        $nummerLower = mb_strtolower(trim($nummer));

        if ($searchLower === $nameLower
            || $searchLower === $labelLower
            || $searchLower === $nummerLower
            || ($nameLower !== '' && mb_strpos($nameLower, $searchLower) !== false)
            || ($labelLower !== '' && mb_strpos($labelLower, $searchLower) !== false)
        ) {
            return true;
        }

        if ($searchCompact === '') {
            return false;
        }

        $nameCompact = self::compactForRoomMatch($name);
        $labelCompact = self::compactForRoomMatch($label);
        $nummerCompact = self::compactForRoomMatch($nummer);

        if ($searchCompact === $nameCompact
            || $searchCompact === $labelCompact
            || $searchCompact === $nummerCompact
        ) {
            return true;
        }

        if ($nameCompact !== '' && str_contains($nameCompact, $searchCompact)) {
            return true;
        }

        if ($labelCompact !== '' && str_contains($labelCompact, $searchCompact)) {
            return true;
        }

        return false;
    }
}
