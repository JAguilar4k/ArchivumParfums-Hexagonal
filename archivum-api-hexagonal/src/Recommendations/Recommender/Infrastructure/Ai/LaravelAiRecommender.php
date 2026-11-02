<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Infrastructure\Ai;

use Illuminate\Support\Carbon;
use Src\Recommendations\Recommender\Domain\Contracts\AiRecommender;
use Src\Recommendations\Recommender\Domain\Exceptions\AiUnavailableException;
use Src\Recommendations\Recommender\Domain\ValueObjects\CustomerPreferences;
use Src\Recommendations\Recommender\Domain\ValueObjects\Recommendation;
use Src\Recommendations\Recommender\Domain\ValueObjects\RecommendationOrigin;
use Src\Recommendations\Recommender\Domain\ValueObjects\SuggestedPerfume;
use Throwable;

/**
 * The AI recommender through laravel/ai, the anticorruption layer between the SDK and the domain (ADR 0008, ADR
 * 0012). The provider and the model come from config/ai.php; no type, exception or field name of the SDK or of the
 * provider leaves this class.
 */
final readonly class LaravelAiRecommender implements AiRecommender
{
    /** Receive the seconds the recommendation waits for the provider, fixed in config/archivum.php (R50). */
    public function __construct(private int $timeoutSeconds) {}

    public function recommend(string $text, array $catalog): Recommendation
    {
        $startedAt = Carbon::now();

        try {
            $response = (new RecommendationAgent($catalog))->prompt($text, timeout: $this->timeoutSeconds);
        } catch (Throwable) {
            // No exception of the SDK or of the HTTP client leaves the adapter: the AI never brings the application down (R50).
            throw new AiUnavailableException($this->ranOutOfTime($startedAt) ? 'recommendations.ai_timed_out' : 'recommendations.ai_failed');
        }

        // laravel/ai repeats once a request the provider answers with 429 or 5xx and a short Retry-After, so an answer
        // can still arrive after the limit. It is discarded like any other late answer.
        if ($this->ranOutOfTime($startedAt)) {
            throw new AiUnavailableException('recommendations.ai_timed_out');
        }

        return $this->recommendation($response->toArray());
    }

    /** Whether the limit of the recommendation passed since the request started. */
    private function ranOutOfTime(Carbon $startedAt): bool
    {
        return $startedAt->diffInSeconds(Carbon::now()) > $this->timeoutSeconds;
    }

    /**
     * Translate the answer to the domain, when it has the shape and the types of the schema: an object with the
     * preferences and a list of at most five perfumes. Anything else is discarded whole, before any value is used.
     * The values themselves, such as an identifier that is not in the catalog, are checked by SuggestionValidator.
     *
     * @param  array<mixed>  $answer
     */
    private function recommendation(array $answer): Recommendation
    {
        $preferences = $answer['preferences'] ?? null;
        $perfumes = $answer['perfumes'] ?? null;

        if (! is_array($preferences) || ! is_array($perfumes) || ! array_is_list($perfumes) || count($perfumes) > Recommendation::MAX_PERFUMES) {
            throw new AiUnavailableException('recommendations.ai_invalid_output');
        }

        return new Recommendation(
            RecommendationOrigin::Ai,
            $this->preferences($preferences),
            array_map($this->perfume(...), $perfumes),
        );
    }

    /** @param  array<mixed>  $preferences */
    private function preferences(array $preferences): CustomerPreferences
    {
        $budget = $preferences['budget'] ?? null;

        if ($budget !== null && ! is_int($budget)) {
            throw new AiUnavailableException('recommendations.ai_invalid_output');
        }

        return new CustomerPreferences(
            $this->notes($preferences['notes'] ?? null),
            $this->optionalText($preferences, 'time_of_day'),
            $this->optionalText($preferences, 'climate'),
            $this->optionalText($preferences, 'occasion'),
            $budget,
        );
    }

    /**
     * The notes, a list of texts the schema requires even when it is empty.
     *
     * @return list<string>
     */
    private function notes(mixed $notes): array
    {
        if (! is_array($notes) || ! array_is_list($notes)) {
            throw new AiUnavailableException('recommendations.ai_invalid_output');
        }

        $names = [];

        foreach ($notes as $note) {
            if (! is_string($note)) {
                throw new AiUnavailableException('recommendations.ai_invalid_output');
            }

            $names[] = $note;
        }

        return $names;
    }

    private function perfume(mixed $perfume): SuggestedPerfume
    {
        if (! is_array($perfume) || ! is_string($perfume['id'] ?? null) || ! is_string($perfume['reason'] ?? null)) {
            throw new AiUnavailableException('recommendations.ai_invalid_output');
        }

        return new SuggestedPerfume($perfume['id'], $perfume['reason']);
    }

    /**
     * A text the schema lets the provider leave out: null when it is absent or null, the text when it is one.
     *
     * @param  array<mixed>  $object
     */
    private function optionalText(array $object, string $key): ?string
    {
        $value = $object[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new AiUnavailableException('recommendations.ai_invalid_output');
        }

        return $value;
    }
}
