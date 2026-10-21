<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Domain\Enums;

enum BlockOrigin: string
{
    case MANUAL = 'manual';
    case GOOGLE = 'google';
}
