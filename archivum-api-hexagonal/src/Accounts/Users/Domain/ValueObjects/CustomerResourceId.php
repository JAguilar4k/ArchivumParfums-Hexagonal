<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\ValueObjects;

use Src\Shared\Domain\ValueObjects\Identifier;

/** Identity of a resource that belongs to a customer, such as its customer record or, later, an appointment. */
final readonly class CustomerResourceId extends Identifier {}
