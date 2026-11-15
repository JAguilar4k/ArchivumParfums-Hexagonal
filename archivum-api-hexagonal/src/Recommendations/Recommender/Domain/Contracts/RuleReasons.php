<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\Contracts;

use Src\Recommendations\Recommender\Domain\ValueObjects\RuleMatch;

interface RuleReasons
{
    /**
     * The short reason in Spanish for a perfume the rules recommend, built only from what it shares with the text. The
     * wording comes from the templates of lang/es, so the domain holds no text for the customer.
     */
    public function write(RuleMatch $match): string;
}
