<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

/** The provider's identifier of a verified notice and whether an earlier arrival of the same event was already kept. */
final readonly class ReceivedPaymentEvent
{
    public function __construct(public string $eventId, public bool $repeated) {}
}
