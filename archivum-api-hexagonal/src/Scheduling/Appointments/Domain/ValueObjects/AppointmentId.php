<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\ValueObjects;

use Src\Shared\Domain\ValueObjects\Identifier;

/** Identity of an appointment. */
final readonly class AppointmentId extends Identifier {}
