<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

/** Who produced a recommendation: the AI provider, or the rules recommender when the AI fails (R50). */
enum RecommendationOrigin: string
{
    case Ai = 'ai';
    case Rules = 'rules';
}
