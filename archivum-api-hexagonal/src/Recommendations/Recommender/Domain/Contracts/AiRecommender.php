<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\Contracts;

use Src\Recommendations\Recommender\Domain\Exceptions\AiUnavailableException;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\Recommendation;

interface AiRecommender
{
    /**
     * Ask the AI provider which perfumes of the catalog fit what the customer describes (R48). Only the customer's
     * text and the catalog travel to the provider (R51). The answer is untrusted: its perfumes and preferences are
     * what the provider said, to be checked against the catalog before they are used (#119).
     *
     * @param  list<CatalogPerfume>  $catalog  The perfumes of the store's showcase.
     *
     * @throws AiUnavailableException when the provider fails, takes longer than the limit or answers something that is not the schema
     */
    public function recommend(string $text, array $catalog): Recommendation;
}
