<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Domain;

use Exception;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;

final class BusinessHours
{
    /** @var array<int, TimeRange[]> */
    private array $slots = [];

    private function __construct(
        private readonly string $businessId
    ) {}

    public static function create(string $businessId): self
    {
        return new self($businessId);
    }

    public function getBusinessId(): string
    {
        return $this->businessId;
    }

    /**
     * Reconstituye el horario desde la BD.
     * Recibe los slots ya armados asumiendo que son válidos.
     *
     * @param  array<int, TimeRange[]>  $slots
     */
    public static function reconstitute(string $businessId, array $slots): self
    {
        $businessHours = new self($businessId);
        $businessHours->slots = $slots;

        return $businessHours;
    }

    public function addSlot(Weekday $day, TimeRange $newRange): void
    {
        $dayValue = $day->value;

        if (! isset($this->slots[$dayValue])) {
            $this->slots[$dayValue] = [];
        }

        // Verifica que no haya traslapes con los tramos existentes de ese día
        foreach ($this->slots[$dayValue] as $existingRange) {
            if ($existingRange->overlaps($newRange)) {
                throw new Exception('scheduling.business_hours.overlapping_slots');
            }
        }

        $this->slots[$dayValue][] = $newRange;
    }

    /**
     * @return array<int, array<int, TimeRange>>
     */
    public function getSlots(): array
    {
        return $this->slots;
    }
}
