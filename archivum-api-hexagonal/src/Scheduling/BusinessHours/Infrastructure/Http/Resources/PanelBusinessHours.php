<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Scheduling\BusinessHours\Domain\Entities\BusinessHours;

/**
 * @property-read BusinessHours $resource
 */
final class PanelBusinessHours extends JsonResource
{
    /** Wrap the opening hours of the business of the session. */
    public function __construct(private readonly BusinessHours $hours)
    {
        parent::__construct($hours);
    }

    /**
     * Expose the tramos with the vocabulary of the business: weekday from 1 (Monday) to 7 (Sunday) and local hours.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tramos = [];

        foreach ($this->hours->getSlots() as $weekday => $ranges) {
            foreach ($ranges as $range) {
                $tramos[] = [
                    'dia_semana' => $weekday,
                    'inicio' => $range->start,
                    'fin' => $range->end,
                ];
            }
        }

        return ['tramos' => $tramos];
    }
}
