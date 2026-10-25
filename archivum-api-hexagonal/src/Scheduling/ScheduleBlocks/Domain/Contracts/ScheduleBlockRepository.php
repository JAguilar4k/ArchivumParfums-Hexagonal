<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Domain\Contracts;

use Src\Scheduling\ScheduleBlocks\Domain\Entities\ScheduleBlock;

interface ScheduleBlockRepository
{
    public function save(ScheduleBlock $block): void;
}
