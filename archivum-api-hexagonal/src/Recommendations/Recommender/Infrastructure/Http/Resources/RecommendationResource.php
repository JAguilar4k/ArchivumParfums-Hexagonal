<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Perfumes\Infrastructure\Http\PerfumeVocabulary;
use Src\Recommendations\Recommender\Application\DTOs\RecommendationResult;

/**
 * @property-read RecommendationResult $resource
 */
final class RecommendationResource extends JsonResource
{
    /** Wrap what the recommender answers. */
    public function __construct(private readonly RecommendationResult $result)
    {
        parent::__construct($result);
    }

    /**
     * Expose who produced the recommendation, the preferences read from the text with the words of the API, and the
     * perfumes, the best first.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'origen' => $this->result->origin === 'ai' ? 'ia' : 'reglas',
            'preferencias' => [
                'notas' => $this->result->notes,
                'momento' => $this->result->timeOfDay === null ? null : PerfumeVocabulary::momentOf($this->result->timeOfDay),
                'clima' => $this->result->climate === null ? null : PerfumeVocabulary::climateOf($this->result->climate),
                'ocasion' => $this->result->occasion,
                'presupuesto' => $this->result->budget,
            ],
            'perfumes' => RecommendedPerfumeResource::collection($this->result->perfumes),
        ];
    }
}
