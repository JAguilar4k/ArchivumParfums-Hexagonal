<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Src\Catalog\Perfumes\Domain\Contracts\VariantCost;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Shared\Domain\ValueObjects\Colones;

/** A supplier cost the test chose: one for every presentation, or the one of a presentation by its identity. */
final readonly class FixedVariantCost implements VariantCost
{
    /** @param array<string, int> $costs Cost in colones by identity of the presentation; the others get the default. */
    public function __construct(private ?int $default = null, private array $costs = []) {}

    public function costOf(PerfumeVariantId $variant): ?Colones
    {
        $cost = $this->costs[$variant->value] ?? $this->default;

        return $cost === null ? null : new Colones($cost);
    }
}
