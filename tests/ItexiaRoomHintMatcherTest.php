<?php

use Hwkdo\IntranetAppAssets\Support\ItexiaRoomHintMatcher;
use Hwkdo\IntranetAppAssets\Support\SeventhingsMinuteApiBudget;
use Hwkdo\SeventhingsLaravel\Models\Raum as ItexiaRaum;
use Illuminate\Support\Collection;

it('lehnt SeventhingsMinuteApiBudget mit maxPerMinute unter 1 ab', function () {
    expect(fn () => new SeventhingsMinuteApiBudget(0))
        ->toThrow(InvalidArgumentException::class);
});

it('findet exakten raumnamen', function () {
    $rooms = new Collection([
        new ItexiaRaum((object) [
            'id' => 42,
            'number' => 'A-100',
            'name' => 'Büro Nord',
        ]),
    ]);

    $matches = ItexiaRoomHintMatcher::findMatchingRooms($rooms, 'Büro Nord');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['id'])->toBe(42)
        ->and($matches[0]['name'])->toBe('Büro Nord');
});

it('liefert bei leerem suchbegriff keine treffer', function () {
    $rooms = new Collection([
        new ItexiaRaum((object) ['id' => 1, 'number' => 'X', 'name' => 'Y']),
    ]);

    expect(ItexiaRoomHintMatcher::findMatchingRooms($rooms, '   '))->toBe([]);
});
