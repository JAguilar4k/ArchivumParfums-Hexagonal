<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Scheduling\BusinessHours\Domain\Entities\BusinessHours;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeOfDay;

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
                    'inicio' => $this->asClock($range->start),
                    'fin' => $this->asClock($range->end),
                ];
            }
        }

        return ['tramos' => $tramos];
    }

    /** Write a time of day as HH:MM, the vocabulary of the panel; the value object keeps only its minutes. */
    private function asClock(TimeOfDay $time): string
    {
        return sprintf('%02d:%02d', intdiv($time->minutes, 60), $time->minutes % 60);
    }
}
