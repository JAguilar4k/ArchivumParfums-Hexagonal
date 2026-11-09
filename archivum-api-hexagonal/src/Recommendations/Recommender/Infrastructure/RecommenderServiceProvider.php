<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Recommendations\Recommender\Application\Contracts\DiscardedSuggestions;
use Src\Recommendations\Recommender\Application\Contracts\RecommendableCatalog;
use Src\Recommendations\Recommender\Domain\Contracts\AiRecommender;
use Src\Recommendations\Recommender\Domain\Contracts\RuleReasons;
use Src\Recommendations\Recommender\Infrastructure\Ai\LaravelAiRecommender;
use Src\Recommendations\Recommender\Infrastructure\Catalog\ShowcaseRecommendableCatalog;
use Src\Recommendations\Recommender\Infrastructure\Logging\LogDiscardedSuggestions;
use Src\Recommendations\Recommender\Infrastructure\Reasons\TranslatedRuleReasons;

final class RecommenderServiceProvider extends ServiceProvider
{
    /**
     * Bind the AI port to its laravel/ai adapter, with the fixed limit of config/archivum.php (R50), the reasons of the
     * rules recommender to the templates of lang/es, the catalog of the recommender to the read models of the showcase
     * (#48) and the record of discarded suggestions to the log.
     */
    public function register(): void
    {
        $this->app->bind(AiRecommender::class, fn (): LaravelAiRecommender => new LaravelAiRecommender(
            (int) config('archivum.ai_timeout_seconds'),
        ));
        $this->app->bind(RuleReasons::class, TranslatedRuleReasons::class);
        $this->app->bind(RecommendableCatalog::class, ShowcaseRecommendableCatalog::class);
        $this->app->bind(DiscardedSuggestions::class, LogDiscardedSuggestions::class);
    }
}
