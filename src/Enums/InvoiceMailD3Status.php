<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum InvoiceMailD3Status: string
{
    case None = 'none';
    case Queued = 'queued';
    case Uploaded = 'uploaded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Nicht hochgeladen',
            self::Queued => 'Upload läuft',
            self::Uploaded => 'In D3',
            self::Failed => 'Upload fehlgeschlagen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'zinc',
            self::Queued => 'sky',
            self::Uploaded => 'green',
            self::Failed => 'red',
        };
    }
}
