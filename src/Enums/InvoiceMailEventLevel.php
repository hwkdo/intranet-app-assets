<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum InvoiceMailEventLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';

    public function color(): string
    {
        return match ($this) {
            self::Info => 'zinc',
            self::Warning => 'amber',
            self::Error => 'red',
        };
    }
}
