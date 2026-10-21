<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Domain\Contracts;

use Src\Scheduling\ScheduleBlocks\Domain\ScheduleBlock;

interface ScheduleBlockRepository
{
    public function save(ScheduleBlock $block): void;
}
