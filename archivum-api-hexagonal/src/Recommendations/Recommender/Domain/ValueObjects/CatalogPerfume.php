<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

final readonly class CatalogPerfume
{
    /**
     * A perfume of the store's showcase as the recommender sees it: only catalog data, never data of a person (R51).
     *
     * @param  list<string>  $notes  The names of its notes.
     * @param  array{time_of_day: list<string>, climate: list<string>, occasion: list<string>}  $tags  The tags by kind, with the values the catalog stores.
     * @param  list<CatalogVariant>  $variants  Only the presentations that can be sold.
     */
    public function __construct(
        public string $id,
        public string $brand,
        public string $name,
        public ?string $concentration,
        public ?string $family,
        public array $notes,
        public array $tags,
        public array $variants,
    ) {}

    /**
     * Whether a presentation of the perfume costs at most the budget: its cheapest presentation decides, the same
     * criterion as the maximum price of the showcase (#48). A perfume without a presentation for sale fits no budget.
     */
    public function fitsBudget(int $budget): bool
    {
        foreach ($this->variants as $variant) {
            if ($variant->price <= $budget) {
                return true;
            }
        }

        return false;
    }
}
