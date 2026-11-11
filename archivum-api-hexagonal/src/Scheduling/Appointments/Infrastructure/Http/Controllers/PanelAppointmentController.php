<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Controllers;

use Src\Scheduling\Appointments\Application\UseCases\RescheduleAppointment;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\RescheduleAppointmentRequest;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\CustomerAppointmentResource;

final readonly class PanelAppointmentController
{
    /**
     * Move a confirmed appointment of the store of the session. The route carries the owner ability of the permission
     * matrix of #102, so a collaborator never reaches it, and business:staff keeps the appointment of another store out.
     */
    public function reschedule(RescheduleAppointmentRequest $request, string $cita, RescheduleAppointment $reschedule): CustomerAppointmentResource
    {
        return new CustomerAppointmentResource($reschedule->execute($request->toCommand($cita)));
    }
}
