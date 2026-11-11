<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\ValueObjects;

use Src\Shared\Domain\ValueObjects\Identifier;

/** Identity of the service a trial appointment books; the appointment keeps it instead of the whole service. */
final readonly class ServiceId extends Identifier {}
