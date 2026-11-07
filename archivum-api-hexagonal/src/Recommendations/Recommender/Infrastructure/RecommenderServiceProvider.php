<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Recommendations\Recommender\Domain\Contracts\AiRecommender;
use Src\Recommendations\Recommender\Infrastructure\Ai\LaravelAiRecommender;

final class RecommenderServiceProvider extends ServiceProvider
{
    /** Bind the AI port to its laravel/ai adapter, with the fixed limit of config/archivum.php (R50). */
    public function register(): void
    {
        $this->app->bind(AiRecommender::class, fn (): LaravelAiRecommender => new LaravelAiRecommender(
            (int) config('archivum.ai_timeout_seconds'),
        ));
    }
}
