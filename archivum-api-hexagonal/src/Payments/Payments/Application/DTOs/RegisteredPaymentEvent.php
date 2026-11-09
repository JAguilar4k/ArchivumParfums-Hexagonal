<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

/** A verified notice kept before it is processed: its gateway and the body the provider sent. */
final readonly class RegisteredPaymentEvent
{
    public function __construct(public string $gateway, public string $payload) {}
}
