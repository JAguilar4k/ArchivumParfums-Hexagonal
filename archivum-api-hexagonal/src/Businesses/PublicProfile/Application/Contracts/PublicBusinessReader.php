<?php

declare(strict_types=1);

namespace Src\Businesses\PublicProfile\Application\Contracts;

use Src\Businesses\PublicProfile\Application\DTOs\PublicBusinessRecord;
use Src\Shared\Domain\ValueObjects\BusinessId;

interface PublicBusinessReader
{
    /** Read the public data of a business, or null when it does not exist. */
    public function find(BusinessId $business): ?PublicBusinessRecord;
}
