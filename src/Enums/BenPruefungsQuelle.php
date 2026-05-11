<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum BenPruefungsQuelle: string
{
    case Legacy = 'legacy';
    case IntranetV3 = 'intranet_v3';
    case Beides = 'beides';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Legacy->value => 'Legacy-Intranet',
            self::IntranetV3->value => 'Intranet V3 (lokal)',
            self::Beides->value => 'Beides (Legacy + Intranet V3)',
        ];
    }
}
