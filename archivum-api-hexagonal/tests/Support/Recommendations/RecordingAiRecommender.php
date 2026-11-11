<?php

declare(strict_types=1);

namespace Tests\Support\Recommendations;

use Src\Recommendations\Recommender\Domain\Contracts\AiRecommender;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\Recommendation;

/**
 * Wraps the real AI adapter and remembers what reached it, so a test sees both what the adapter received and the
 * request it sent to the simulated provider (#121).
 */
final class RecordingAiRecommender implements AiRecommender
{
    /** @var list<array{text: string, catalog: list<CatalogPerfume>}> */
    public private(set) array $received = [];

    public function __construct(private readonly AiRecommender $adapter) {}

    public function recommend(string $text, array $catalog): Recommendation
    {
        $this->received[] = ['text' => $text, 'catalog' => $catalog];

        return $this->adapter->recommend($text, $catalog);
    }
}
