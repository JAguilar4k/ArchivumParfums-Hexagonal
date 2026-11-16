<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Application\UseCases;

use Src\Scheduling\BusinessHours\Domain\Contracts\BusinessHoursRepository;
use Src\Scheduling\BusinessHours\Domain\Entities\BusinessHours;

/** Read the opening hours of the business of the session. */
final readonly class GetBusinessHours
{
    /** Receive the repository. */
    public function __construct(private BusinessHoursRepository $hours) {}

    /** The tramos of the business of the session, ordered by weekday and opening hour. */
    public function execute(): BusinessHours
    {
        return $this->hours->all();
    }
}
