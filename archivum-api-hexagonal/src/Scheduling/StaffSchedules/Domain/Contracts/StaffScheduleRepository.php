<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Domain\Contracts;

use Src\Scheduling\StaffSchedules\Domain\StaffSchedule;

interface StaffScheduleRepository
{
    public function save(StaffSchedule $staffSchedule): void;
}
