<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum InvoiceMailStatus: string
{
    case Received = 'received';
    case Classifying = 'classifying';
    case Irrelevant = 'irrelevant';
    case Analyzing = 'analyzing';
    case Analyzed = 'analyzed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Empfangen',
            self::Classifying => 'Wird klassifiziert',
            self::Irrelevant => 'Irrelevant',
            self::Analyzing => 'Wird ausgewertet',
            self::Analyzed => 'Ausgewertet',
            self::Failed => 'Fehler',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received, self::Classifying, self::Analyzing => 'sky',
            self::Irrelevant => 'zinc',
            self::Analyzed => 'green',
            self::Failed => 'red',
        };
    }

    public function isInProgress(): bool
    {
        return in_array($this, [self::Received, self::Classifying, self::Analyzing], true);
    }
}
