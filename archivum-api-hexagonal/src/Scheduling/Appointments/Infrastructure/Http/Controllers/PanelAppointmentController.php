<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Src\Scheduling\Appointments\Application\UseCases\BookAppointment;
use Src\Scheduling\Appointments\Application\UseCases\CancelAppointment;
use Src\Scheduling\Appointments\Application\UseCases\RescheduleAppointment;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\CancelAppointmentRequest;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\RescheduleAppointmentRequest;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\StorePanelAppointment;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\BookedAppointmentResource;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\CustomerAppointmentResource;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class PanelAppointmentController
{
    /**
     * Book a trial appointment for a customer of the store of the session. The route carries the ability the matrix of
     * #102 gives only to the owner, and business:staff pins the transaction to the store of the session, so a store
     * named in the body never changes the store of the appointment.
     *
     * @throws ValidationException when a field of the request cannot be booked
     */
    public function store(StorePanelAppointment $request, BookAppointment $bookAppointment): JsonResponse
    {
        try {
            $appointment = $bookAppointment->execute($request->toCommand());
        } catch (InvalidValueException $exception) {
            throw $this->invalid($exception);
        }

        // The customer reads the appointment in its own listing; the panel has no detail of an appointment until #137,
        // so the answer carries no Location yet.
        return (new BookedAppointmentResource($appointment))->response($request)->setStatusCode(201);
    }

    /**
     * Move a confirmed appointment of the store of the session. The route carries the owner ability of the permission
     * matrix of #102, so a collaborator never reaches it, and business:staff keeps the appointment of another store out.
     *
     * @throws ValidationException when the new start cannot be booked
     */
    public function reschedule(RescheduleAppointmentRequest $request, string $cita, RescheduleAppointment $reschedule): CustomerAppointmentResource
    {
        try {
            return new CustomerAppointmentResource($reschedule->execute($request->toCommand($cita)));
        } catch (InvalidValueException $exception) {
            throw $this->invalid($exception);
        }
    }

    /**
     * Cancel a confirmed appointment of the store of the session. The store has no deadline of its own in the
     * specification, so it may cancel until the appointment starts.
     */
    public function cancel(CancelAppointmentRequest $request, string $cita, CancelAppointment $cancel): CustomerAppointmentResource
    {
        return new CustomerAppointmentResource($cancel->execute($request->toCommand($cita, customerDeadline: false)));
    }

    /**
     * The 422 of a value the booking or the move refused, under the field of the request it comes from; an invalid
     * value of any other kind keeps its own answer.
     */
    private function invalid(InvalidValueException $exception): ValidationException
    {
        $field = match ($exception->translationKey()) {
            'scheduling.booking.invalid_start', 'scheduling.booking.outside_window',
            'scheduling.availability.invalid_date' => 'inicia_en',
            'scheduling.booking.customer_unavailable' => 'cliente_id',
            'scheduling.booking.service_unavailable', 'scheduling.availability.service_not_bookable' => 'servicio_id',
            'scheduling.booking.person_unavailable', 'scheduling.availability.person_not_available' => 'persona_id',
            'scheduling.booking.perfume_unavailable', 'scheduling.appointment.repeated_perfume',
            'scheduling.appointment.too_many_perfumes' => 'perfumes',
            default => throw $exception,
        };

        return ValidationException::withMessages([$field => [__($exception->translationKey(), $exception->parameters())]]);
    }
}
