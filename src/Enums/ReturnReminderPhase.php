<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum ReturnReminderPhase: string
{
    case Upcoming1 = 'upcoming_1';
    case Upcoming2 = 'upcoming_2';
    case Overdue = 'overdue';

    public function isOverdue(): bool
    {
        return $this === self::Overdue;
    }
}
