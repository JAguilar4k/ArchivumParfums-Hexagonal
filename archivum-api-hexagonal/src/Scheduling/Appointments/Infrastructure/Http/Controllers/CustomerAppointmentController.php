<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Scheduling\Appointments\Application\UseCases\BookAppointment;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\StoreAppointment;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\BookedAppointmentResource;

final readonly class CustomerAppointmentController
{
    /** Book a trial appointment of the customer of the session in the store of the address. */
    public function store(StoreAppointment $request, BookAppointment $bookAppointment): JsonResponse
    {
        $appointment = $bookAppointment->execute($request->toCommand());

        return (new BookedAppointmentResource($appointment))->response($request)
            ->setStatusCode(201)
            ->header('Location', url('/api/v1/cuenta/citas/'.$appointment->id));
    }
}
