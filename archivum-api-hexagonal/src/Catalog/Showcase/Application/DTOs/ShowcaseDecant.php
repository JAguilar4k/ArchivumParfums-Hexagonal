<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Application\DTOs;

/**
 * A decant size that an open bottle can serve. The ml of the presentation of the bottle go with it, because the price
 * comes from that presentation (R3): two open bottles of different presentations offer the same size at two prices.
 */
final readonly class ShowcaseDecant
{
    public function __construct(public string $bottleId, public int $presentationMl, public int $ml, public int $price) {}
}
