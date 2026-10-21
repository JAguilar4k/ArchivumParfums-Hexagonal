<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;

/** The business a seeder is filling, so the use cases of the module work for it as they do for the session of a request. */
final readonly class SeededBusinessContext implements BusinessContext
{
    public function __construct(private BusinessId $business) {}

    public function current(): BusinessId
    {
        return $this->business;
    }
}
