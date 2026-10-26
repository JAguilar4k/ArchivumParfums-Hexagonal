<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

final readonly class RefundResult
{
    /** Normalize the provider response without exposing its JSON. */
    public function __construct(public string $providerReference, public string $status) {}
}
