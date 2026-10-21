<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Persistence\Observers;

use Illuminate\Contracts\Events\Dispatcher;
use Src\Businesses\Profile\Domain\Events\BusinessMarginChanged;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;

/**
 * Announce a change of the margin per bottle when the row of a business is updated. It runs inside the transaction that
 * saves the business, so whoever listens works in that same transaction.
 */
final readonly class BusinessMarginObserver
{
    private const string KEY = 'bottle_margin_colones';

    public function __construct(private Dispatcher $events) {}

    /** Dispatch the event only when the margin stored is not the one that was stored before. */
    public function updated(BusinessModel $business): void
    {
        if (! $business->wasChanged('settings')) {
            return;
        }

        $previous = $this->marginOf($business->getOriginal('settings'));
        $current = $this->marginOf($business->settings);

        if ($previous !== $current) {
            $this->events->dispatch(new BusinessMarginChanged($business->id, $previous, $current));
        }
    }

    /** The margin of the settings stored, or the default when the business never changed it. */
    private function marginOf(mixed $settings): int
    {
        return is_array($settings) && isset($settings[self::KEY])
            ? (int) $settings[self::KEY]
            : BusinessSettings::DEFAULT_BOTTLE_MARGIN_COLONES;
    }
}
