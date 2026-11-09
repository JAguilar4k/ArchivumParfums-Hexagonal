<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Application\Contracts;

use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;

interface RecommendableCatalog
{
    /**
     * The perfumes of the showcase of the store of the address, the only ones the recommender may suggest: the same
     * perfumes and presentations a visitor sees (#48), with their notes and tags. It carries catalog data only, never
     * data of a person (R51).
     *
     * @return list<CatalogPerfume>
     */
    public function perfumes(): array;
}
