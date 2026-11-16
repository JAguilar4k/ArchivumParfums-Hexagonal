<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Domain\Contracts;

use Src\Scheduling\BusinessHours\Domain\Entities\BusinessHours;
use Src\Scheduling\BusinessHours\Domain\ValueObjects\BusinessHoursId;

/**
 * Port of the opening hours of one business. Every query reaches only the rows of the business of the context, and the
 * row-level policy of PostgreSQL is the second barrier.
 */
interface BusinessHoursRepository
{
    /** Generate a time-ordered identity for a new schedule; the adapter owns the generation, the application does not. */
    public function nextId(): BusinessHoursId;

    /**
     * Replace every tramo of the business of the context with the ones of the aggregate, in one transaction: the store
     * keeps its old hours when the new ones are refused.
     */
    public function replace(BusinessHours $businessHours): void;

    /** The tramos of the business of the context, ordered by weekday and opening hour. */
    public function all(): BusinessHours;
}
