<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Src\Scheduling\Appointments\Application\UseCases\BookAppointment;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\StoreAppointment;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\BookedAppointmentResource;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class CustomerAppointmentController
{
    /**
     * Book a trial appointment of the customer of the session in the store of the address.
     *
     * @throws ValidationException when a field of the request cannot be booked
     */
    public function store(StoreAppointment $request, BookAppointment $bookAppointment): JsonResponse
    {
        try {
            $appointment = $bookAppointment->execute($request->toCommand());
        } catch (InvalidValueException $exception) {
            throw $this->invalid($exception);
        }

        return (new BookedAppointmentResource($appointment))->response($request)
            ->setStatusCode(201)
            ->header('Location', url('/api/v1/cuenta/citas/'.$appointment->id));
    }

    /**
     * The 422 of a value the booking refused, under the field of the request it comes from; an invalid value of any
     * other kind keeps its own answer.
     */
    private function invalid(InvalidValueException $exception): ValidationException
    {
        $field = match ($exception->translationKey()) {
            'scheduling.booking.invalid_start', 'scheduling.booking.outside_window',
            'scheduling.availability.invalid_date' => 'inicia_en',
            'scheduling.booking.service_unavailable', 'scheduling.availability.service_not_bookable' => 'servicio_id',
            'scheduling.booking.person_unavailable', 'scheduling.availability.person_not_available' => 'persona_id',
            'scheduling.booking.perfume_unavailable', 'scheduling.appointment.repeated_perfume',
            'scheduling.appointment.too_many_perfumes' => 'perfumes',
            default => throw $exception,
        };

        return ValidationException::withMessages([$field => [__($exception->translationKey(), $exception->parameters())]]);
    }
}
