<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum AssetReturnSource: string
{
    case Holder = 'holder';
    case Loan = 'loan';

    public function isLoan(): bool
    {
        return $this === self::Loan;
    }

    public function label(): string
    {
        return match ($this) {
            self::Holder => 'Rückgabe',
            self::Loan => 'Leihe',
        };
    }
}
