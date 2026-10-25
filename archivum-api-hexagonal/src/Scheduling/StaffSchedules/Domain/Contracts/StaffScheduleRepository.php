<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Domain\Contracts;

use Src\Scheduling\StaffSchedules\Domain\Entities\StaffSchedule;

interface StaffScheduleRepository
{
    public function save(StaffSchedule $staffSchedule): void;
}
