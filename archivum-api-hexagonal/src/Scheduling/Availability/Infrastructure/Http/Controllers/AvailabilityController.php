<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Infrastructure\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Scheduling\Availability\Application\UseCases\GetAvailability;
use Src\Scheduling\Availability\Infrastructure\Http\Requests\IndexAvailability;
use Src\Scheduling\Availability\Infrastructure\Http\Resources\AvailableSlotResource;

final readonly class AvailabilityController
{
    /** List the free slots of one service on one day of the store of the address, without a session. */
    public function index(IndexAvailability $request, GetAvailability $availability): AnonymousResourceCollection
    {
        $slots = $availability->execute(
            $request->string('servicio')->toString(),
            $request->string('fecha')->toString(),
            $request->filled('persona') ? $request->string('persona')->toString() : null,
        );

        return AvailableSlotResource::collection($slots);
    }
}
