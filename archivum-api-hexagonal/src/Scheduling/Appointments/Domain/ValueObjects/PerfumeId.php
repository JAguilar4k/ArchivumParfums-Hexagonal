<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\ValueObjects;

use Src\Shared\Domain\ValueObjects\Identifier;

/** Identity of a perfume the customer wants to try in a trial appointment. */
final readonly class PerfumeId extends Identifier {}
