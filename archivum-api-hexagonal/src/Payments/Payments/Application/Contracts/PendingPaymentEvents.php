<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

interface PendingPaymentEvents
{
    /** Process a kept notice apart from the request that brought it, so the provider gets its answer at once. */
    public function process(string $id): void;
}
