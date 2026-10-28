<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum InvoiceMailMatchStatus: string
{
    case Unique = 'unique';
    case Ambiguous = 'ambiguous';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Unique => 'Eindeutig',
            self::Ambiguous => 'Mehrdeutig',
            self::None => 'Kein Treffer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unique => 'green',
            self::Ambiguous => 'amber',
            self::None => 'red',
        };
    }
}
