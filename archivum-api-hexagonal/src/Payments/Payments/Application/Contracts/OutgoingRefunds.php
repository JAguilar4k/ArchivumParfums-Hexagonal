<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

interface OutgoingRefunds
{
    /**
     * Ask the provider for a registered refund apart from the operation that registered it, so neither a cancellation
     * nor a webhook waits for the provider (R40).
     */
    public function send(string $refundId, string $businessId): void;
}
