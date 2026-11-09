<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Infrastructure\Http\Controllers;

use Src\Recommendations\Recommender\Application\UseCases\RecommendPerfumes;
use Src\Recommendations\Recommender\Infrastructure\Http\Requests\StoreRecommendation;
use Src\Recommendations\Recommender\Infrastructure\Http\Resources\RecommendationResource;

final readonly class RecommendationController
{
    /** Recommend up to five perfumes of the showcase for what the customer describes, by the AI or, if it fails, by the rules. */
    public function store(StoreRecommendation $request, RecommendPerfumes $recommendPerfumes): RecommendationResource
    {
        return new RecommendationResource($recommendPerfumes->execute($request->text()));
    }
}
