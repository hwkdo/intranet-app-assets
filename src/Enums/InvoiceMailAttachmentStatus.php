<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum InvoiceMailAttachmentStatus: string
{
    case Pending = 'pending';
    case Analyzing = 'analyzing';
    case Analyzed = 'analyzed';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Offen',
            self::Analyzing => 'Wird ausgewertet',
            self::Analyzed => 'Ausgewertet',
            self::Skipped => 'Übersprungen',
            self::Failed => 'Fehler',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Analyzing], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Analyzing => 'sky',
            self::Analyzed => 'green',
            self::Skipped => 'zinc',
            self::Failed => 'red',
        };
    }
}
