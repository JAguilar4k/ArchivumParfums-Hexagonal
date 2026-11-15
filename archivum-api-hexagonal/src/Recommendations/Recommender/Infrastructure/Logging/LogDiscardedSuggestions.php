<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Infrastructure\Logging;

use Illuminate\Support\Facades\Log;
use Src\Recommendations\Recommender\Application\Contracts\DiscardedSuggestions;

final readonly class LogDiscardedSuggestions implements DiscardedSuggestions
{
    /** Write how many values and perfumes the validation discarded, counted by cause, to the log of the application. */
    public function record(array $causes): void
    {
        $byCause = [];
        foreach ($causes as $cause) {
            $byCause[$cause->value] = ($byCause[$cause->value] ?? 0) + 1;
        }

        Log::info('The validation discarded part of the AI suggestion.', [
            'discarded' => count($causes),
            'causes' => $byCause,
        ]);
    }
}
