<?php

namespace Src\Scheduling\Shared\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

class TimeSlotOverlapsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('scheduling.time_slot_overlaps');
    }
}