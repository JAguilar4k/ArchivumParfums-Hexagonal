<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

use DateTimeImmutable;
use Src\Payments\Payments\Application\DTOs\RefundReference;

interface PendingRefunds
{
    /**
     * Find, across every business and up to the limit, the pending refunds the provider already accepted that waited
     * Payment::MINUTES_FOR_THE_NOTICE minutes for their confirmation; the oldest of each business come first.
     *
     * @return list<RefundReference>
     */
    public function dueAt(DateTimeImmutable $now, int $limit): array;
}
