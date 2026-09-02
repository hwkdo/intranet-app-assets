<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum ReturnScheduleType: string
{
    case Immediate = 'immediate';
    case Scheduled = 'scheduled';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Immediate->value => 'Sofort',
            self::Scheduled->value => 'Geplant',
        ];
    }
}
