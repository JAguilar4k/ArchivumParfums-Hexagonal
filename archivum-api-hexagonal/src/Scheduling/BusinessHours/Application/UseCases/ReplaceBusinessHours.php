<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Application\UseCases;

use Src\Scheduling\BusinessHours\Application\DTOs\ReplaceBusinessHoursCommand;
use Src\Scheduling\BusinessHours\Domain\Contracts\BusinessHoursRepository;
use Src\Scheduling\BusinessHours\Domain\Entities\BusinessHours;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

/**
 * Replace the whole opening hours of the business of the session in one transaction. The aggregate refuses two
 * overlapping tramos of the same day, and the exclusion constraint of the table backs that up.
 */
final readonly class ReplaceBusinessHours
{
    /** Receive the repository, the business of the session and the transaction ports. */
    public function __construct(
        private BusinessHoursRepository $hours,
        private BusinessContext $businessContext,
        private TransactionRunner $transaction,
    ) {}

    /** Build the schedule with the tramos of the command and store it, replacing the previous one. */
    public function execute(ReplaceBusinessHoursCommand $command): BusinessHours
    {
        return $this->transaction->run(function () use ($command): BusinessHours {
            $hours = BusinessHours::create($this->hours->nextId(), $this->businessContext->current());

            foreach ($command->slots as $slot) {
                $hours->addSlot(Weekday::from($slot->weekday), TimeRange::create($slot->opensAt, $slot->closesAt));
            }

            $this->hours->replace($hours);

            return $hours;
        });
    }
}
