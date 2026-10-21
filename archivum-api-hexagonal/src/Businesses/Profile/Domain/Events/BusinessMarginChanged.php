<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\Events;

/** The margin per bottle of a business changed. Other modules listen to it; the business does not know them. */
final readonly class BusinessMarginChanged
{
    public function __construct(
        public string $businessId,
        public int $previousMarginColones,
        public int $marginColones,
    ) {}
}
