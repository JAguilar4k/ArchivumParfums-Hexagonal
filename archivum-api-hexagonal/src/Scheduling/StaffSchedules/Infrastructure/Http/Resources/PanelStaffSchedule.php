<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Scheduling\StaffSchedules\Domain\Entities\StaffSchedule;

/**
 * @property-read StaffSchedule $resource
 */
final class PanelStaffSchedule extends JsonResource
{
    /** Wrap the working hours of one person of the staff. */
    public function __construct(private readonly StaffSchedule $schedule)
    {
        parent::__construct($schedule);
    }

    /**
     * Expose the tramos with the vocabulary of the business: weekday from 1 (Monday) to 7 (Sunday) and local hours.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tramos = [];

        foreach ($this->schedule->getSlots() as $weekday => $ranges) {
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
