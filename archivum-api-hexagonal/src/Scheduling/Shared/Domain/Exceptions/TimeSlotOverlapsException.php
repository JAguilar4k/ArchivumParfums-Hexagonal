<?php

declare(strict_types=1);

namespace Src\Scheduling\Shared\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

class TimeSlotOverlapsException extends DomainException {}
