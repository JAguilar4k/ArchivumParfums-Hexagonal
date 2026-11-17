<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Infrastructure\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Src\Scheduling\Availability\Application\UseCases\GetAvailability;
use Src\Scheduling\Availability\Infrastructure\Http\Requests\IndexAvailability;
use Src\Scheduling\Availability\Infrastructure\Http\Resources\AvailableSlotResource;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class AvailabilityController
{
    /**
     * List the free slots of one service on one day of the store of the address, without a session.
     *
     * @throws ValidationException when the date, the service or the person cannot be booked in this store
     */
    public function index(IndexAvailability $request, GetAvailability $availability): AnonymousResourceCollection
    {
        try {
            $slots = $availability->execute(
                $request->string('servicio')->toString(),
                $request->string('fecha')->toString(),
                $request->filled('persona') ? $request->string('persona')->toString() : null,
            );
        } catch (InvalidValueException $exception) {
            $field = match ($exception->translationKey()) {
                'scheduling.availability.invalid_date' => 'fecha',
                'scheduling.availability.service_not_bookable' => 'servicio',
                'scheduling.availability.person_not_available' => 'persona',
                default => throw $exception,
            };
            throw ValidationException::withMessages([$field => [__($exception->translationKey())]]);
        }

        return AvailableSlotResource::collection($slots);
    }
}
