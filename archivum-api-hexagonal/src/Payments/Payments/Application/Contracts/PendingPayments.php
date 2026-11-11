<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

use DateTimeImmutable;
use Src\Payments\Payments\Application\DTOs\PaymentReference;

interface PendingPayments
{
    /**
     * Find, across every business and up to the limit, the pending payments with a reference of the provider that
     * already waited Payment::MINUTES_FOR_THE_NOTICE minutes for their notice, and the expired payments of expired orders
     * that the expiry closed less than Payment::HOURS_FOR_A_LATE_CHARGE hours ago (#192); the oldest of each business
     * come first.
     *
     * @return list<PaymentReference>
     */
    public function dueAt(DateTimeImmutable $now, int $limit): array;
}
