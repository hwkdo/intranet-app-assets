<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum InvoiceMailMailboxAction: string
{
    case None = 'none';
    case MarkRead = 'mark_read';
    case MoveToFolder = 'move_to_folder';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Nichts machen',
            self::MarkRead => 'Als gelesen markieren',
            self::MoveToFolder => 'In Ordner verschieben',
        };
    }
}
