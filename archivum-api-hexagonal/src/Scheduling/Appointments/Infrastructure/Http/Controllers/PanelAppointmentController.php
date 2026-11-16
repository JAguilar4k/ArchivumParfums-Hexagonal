<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Scheduling\Appointments\Application\UseCases\BookAppointment;
use Src\Scheduling\Appointments\Application\UseCases\CancelAppointment;
use Src\Scheduling\Appointments\Application\UseCases\RescheduleAppointment;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\CancelAppointmentRequest;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\RescheduleAppointmentRequest;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\StorePanelAppointment;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\BookedAppointmentResource;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\CustomerAppointmentResource;

final readonly class PanelAppointmentController
{
    /**
     * Book a trial appointment for a customer of the store of the session. The route carries the ability the matrix of
     * #102 gives only to the owner, and business:staff pins the transaction to the store of the session, so a store
     * named in the body never changes the store of the appointment.
     */
    public function store(StorePanelAppointment $request, BookAppointment $bookAppointment): JsonResponse
    {
        $appointment = $bookAppointment->execute($request->toCommand());

        // The customer reads the appointment in its own listing; the panel has no detail of an appointment until #137,
        // so the answer carries no Location yet.
        return (new BookedAppointmentResource($appointment))->response($request)->setStatusCode(201);
    }

    /**
     * Move a confirmed appointment of the store of the session. The route carries the owner ability of the permission
     * matrix of #102, so a collaborator never reaches it, and business:staff keeps the appointment of another store out.
     */
    public function reschedule(RescheduleAppointmentRequest $request, string $cita, RescheduleAppointment $reschedule): CustomerAppointmentResource
    {
        return new CustomerAppointmentResource($reschedule->execute($request->toCommand($cita)));
    }

    /**
     * Cancel a confirmed appointment of the store of the session. The store has no deadline of its own in the
     * specification, so it may cancel until the appointment starts.
     */
    public function cancel(CancelAppointmentRequest $request, string $cita, CancelAppointment $cancel): CustomerAppointmentResource
    {
        return new CustomerAppointmentResource($cancel->execute($request->toCommand($cita, customerDeadline: false)));
    }
}
