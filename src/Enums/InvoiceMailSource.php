<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum InvoiceMailSource: string
{
    case Webhook = 'webhook';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Webhook => 'Webhook',
            self::Import => 'Import',
        };
    }
}
