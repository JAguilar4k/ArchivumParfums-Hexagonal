<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\ValueObjects;

use Src\Shared\Domain\ValueObjects\Identifier;

/** Identity of the customer record of a business, the one an appointment is booked for. */
final readonly class CustomerId extends Identifier {}
