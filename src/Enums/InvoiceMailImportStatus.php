<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum InvoiceMailImportStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Wartet',
            self::Running => 'Läuft',
            self::Completed => 'Abgeschlossen',
            self::Failed => 'Fehler',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued, self::Running => 'sky',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }
}
