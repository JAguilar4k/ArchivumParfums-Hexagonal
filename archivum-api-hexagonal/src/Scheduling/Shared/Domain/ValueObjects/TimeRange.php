<?php

declare(strict_types=1);

namespace Src\Scheduling\Shared\Domain\ValueObjects;

use Exception;

final class TimeRange
{
    private function __construct(
        public readonly string $start,
        public readonly string $end
    ) {
        // Validación de la invariante: rechaza fin igual o anterior al inicio
        if ($this->end <= $this->start) {
            throw new Exception('scheduling.time_range.invalid_order');
        }
    }

    public static function create(string $start, string $end): self
    {
        return new self($start, $end);
    }

    /**
     * Comprueba si este tramo se traslapa con otro.
     * Evalúa como intervalo semiabierto [inicio, fin).
     */
    public function overlaps(TimeRange $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }
}
