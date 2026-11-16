<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Infrastructure\Http\Controllers;

use Src\Scheduling\BusinessHours\Application\UseCases\GetBusinessHours;
use Src\Scheduling\BusinessHours\Application\UseCases\ReplaceBusinessHours;
use Src\Scheduling\BusinessHours\Infrastructure\Http\Requests\ReplaceBusinessHours as ReplaceBusinessHoursRequest;
use Src\Scheduling\BusinessHours\Infrastructure\Http\Resources\PanelBusinessHours;

final readonly class BusinessHoursController
{
    /** Show the opening hours of the business of the session, ordered by weekday and opening hour. */
    public function show(GetBusinessHours $getBusinessHours): PanelBusinessHours
    {
        return new PanelBusinessHours($getBusinessHours->execute());
    }

    /** Replace the whole opening hours of the business of the session in one transaction. */
    public function replace(ReplaceBusinessHoursRequest $request, ReplaceBusinessHours $replaceBusinessHours): PanelBusinessHours
    {
        return new PanelBusinessHours($replaceBusinessHours->execute($request->toCommand()));
    }
}
