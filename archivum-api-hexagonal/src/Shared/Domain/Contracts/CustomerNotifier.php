<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\CustomerNotice;

interface CustomerNotifier
{
    /** @param array<string, string> $details */
    public function schedule(CustomerNotice $notice, string $businessId, string $customerId, string $reference, array $details = []): void;
}
