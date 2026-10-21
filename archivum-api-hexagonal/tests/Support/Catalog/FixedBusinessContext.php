<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;

/** A business context that answers the business the test chose, and lets the test change it. */
final class FixedBusinessContext implements BusinessContext
{
    public function __construct(private BusinessId $business) {}

    public function current(): BusinessId
    {
        return $this->business;
    }

    /** Make the next operations belong to another business. */
    public function switchTo(BusinessId $business): void
    {
        $this->business = $business;
    }
}
