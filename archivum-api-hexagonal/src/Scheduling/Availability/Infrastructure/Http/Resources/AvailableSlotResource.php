<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Scheduling\Availability\Application\DTOs\AvailablePerson;
use Src\Scheduling\Availability\Application\DTOs\AvailableSlot;

/**
 * @property-read AvailableSlot $resource
 */
final class AvailableSlotResource extends JsonResource
{
    /** Wrap one free slot of the day. */
    public function __construct(private readonly AvailableSlot $slot)
    {
        parent::__construct($slot);
    }

    /**
     * Expose the slot as the public contract names it: the two ends in ISO 8601 with the offset of the store and only
     * the identity and the name of each person who can attend it.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'inicia_en' => $this->slot->startsAt->format(DATE_ATOM),
            'termina_en' => $this->slot->endsAt->format(DATE_ATOM),
            'personas' => array_map(
                fn (AvailablePerson $person): array => ['id' => $person->id, 'nombre' => $person->name],
                $this->slot->people,
            ),
        ];
    }
}
