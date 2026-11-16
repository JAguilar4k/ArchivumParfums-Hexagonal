<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Application\UseCases;

use Src\Scheduling\ScheduleBlocks\Domain\Contracts\ScheduleBlockRepository;
use Src\Scheduling\ScheduleBlocks\Domain\Exceptions\BlockFromCalendarException;
use Src\Scheduling\ScheduleBlocks\Domain\Exceptions\ScheduleBlockNotFoundException;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\BlockOrigin;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\ScheduleBlockId;
use Src\Shared\Domain\Contracts\TransactionRunner;

/** Delete a block of the agenda of the business of the session; a block of the calendar cannot be deleted here. */
final readonly class DeleteScheduleBlock
{
    /** Receive the repository and the transaction port. */
    public function __construct(
        private ScheduleBlockRepository $blocks,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Remove the block.
     *
     * @throws ScheduleBlockNotFoundException
     * @throws BlockFromCalendarException
     */
    public function execute(string $blockId): void
    {
        $this->transaction->run(function () use ($blockId): void {
            $block = $this->blocks->find(new ScheduleBlockId($blockId))
                ?? throw ScheduleBlockNotFoundException::create();

            if ($block->origin === BlockOrigin::GOOGLE) {
                throw BlockFromCalendarException::create();
            }

            $this->blocks->delete($block);
        });
    }
}
