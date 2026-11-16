<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Infrastructure\Http\Controllers;

use Src\Scheduling\StaffSchedules\Application\UseCases\GetStaffSchedule;
use Src\Scheduling\StaffSchedules\Application\UseCases\ReplaceStaffSchedule;
use Src\Scheduling\StaffSchedules\Infrastructure\Http\Requests\ReplaceStaffSchedule as ReplaceStaffScheduleRequest;
use Src\Scheduling\StaffSchedules\Infrastructure\Http\Resources\PanelStaffSchedule;

final readonly class StaffScheduleController
{
    /** Show the working hours of one person of the staff of the session business. */
    public function show(string $persona, GetStaffSchedule $getStaffSchedule): PanelStaffSchedule
    {
        return new PanelStaffSchedule($getStaffSchedule->execute($persona));
    }

    /** Replace the whole working hours of one person of the staff of the session business, in one transaction. */
    public function replace(
        ReplaceStaffScheduleRequest $request,
        string $persona,
        ReplaceStaffSchedule $replaceStaffSchedule,
    ): PanelStaffSchedule {
        return new PanelStaffSchedule($replaceStaffSchedule->execute($request->toCommand($persona)));
    }
}
