<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

/**
 * Why the validation discarded a value of the preferences or a suggested perfume (R49). The log names each discard by
 * its cause, never by the customer's text (#121).
 */
enum DiscardCause: string
{
    /** The perfume is not in the catalog sent: it does not exist, it belongs to another store or it is not active. */
    case NotInCatalog = 'not_in_catalog';

    /** The perfume is already in the recommendation. */
    case Repeated = 'repeated';

    /** No presentation of the perfume costs at most the budget. */
    case OverBudget = 'over_budget';

    /** The reason is empty or only spaces. */
    case EmptyReason = 'empty_reason';

    /** The reason is longer than SuggestionValidator::MAX_REASON_LENGTH characters. */
    case ReasonTooLong = 'reason_too_long';

    /** The time of day is not day, afternoon or night. */
    case InvalidTimeOfDay = 'invalid_time_of_day';

    /** The climate is not warm, temperate or cold. */
    case InvalidClimate = 'invalid_climate';

    /** The budget is not a positive whole number of colones. */
    case InvalidBudget = 'invalid_budget';
}
