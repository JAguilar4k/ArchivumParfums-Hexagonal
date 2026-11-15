<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

final readonly class RefundResult
{
    /**
     * Normalize the provider response without exposing its JSON. The reference is null only when the provider refused
     * the refund without creating one.
     */
    public function __construct(public ?string $providerReference, public string $status) {}
}
