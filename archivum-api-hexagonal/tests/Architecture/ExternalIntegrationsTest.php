<?php

declare(strict_types=1);

arch('only the AI adapter of the recommender uses laravel/ai, so changing the SDK touches a single adapter', function () {
    expect('Laravel\Ai')->toOnlyBeUsedIn('Src\Recommendations\Recommender\Infrastructure\Ai');
});
