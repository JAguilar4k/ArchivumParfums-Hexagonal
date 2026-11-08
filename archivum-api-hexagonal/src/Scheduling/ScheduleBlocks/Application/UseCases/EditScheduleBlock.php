<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Application\UseCases;

use Src\Scheduling\ScheduleBlocks\Application\DTOs\EditScheduleBlockCommand;
use Src\Scheduling\ScheduleBlocks\Domain\Contracts\ScheduleBlockRepository;
use Src\Scheduling\ScheduleBlocks\Domain\Entities\ScheduleBlock;
use Src\Scheduling\ScheduleBlocks\Domain\Exceptions\BlockFromCalendarException;
use Src\Scheduling\ScheduleBlocks\Domain\Exceptions\ScheduleBlockNotFoundException;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\BlockOrigin;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\ScheduleBlockId;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * Change the fields sent of a block of the business of the session. A block that comes from the calendar is refused with
 * a conflict: only #183 changes it, through the synchronization.
 */
final readonly class EditScheduleBlock
{
    /** Receive the repository and the transaction port. */
    public function __construct(
        private ScheduleBlockRepository $blocks,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Apply the changes of the command and store the block.
     *
     * @throws ScheduleBlockNotFoundException
     * @throws BlockFromCalendarException
     */
    public function execute(EditScheduleBlockCommand $command): ScheduleBlock
    {
        return $this->transaction->run(function () use ($command): ScheduleBlock {
            $block = $this->blocks->find(new ScheduleBlockId($command->blockId))
                ?? throw ScheduleBlockNotFoundException::create();

            if ($block->origin === BlockOrigin::GOOGLE) {
                throw BlockFromCalendarException::create();
            }

            $changes = $command->changes;
            $member = $block->memberId;

            if (array_key_exists('member_id', $changes)) {
                $member = $changes['member_id'] === null ? null : new MemberId($changes['member_id']);

                if ($member !== null && ! $this->blocks->memberExists($member)) {
                    throw new InvalidValueException('scheduling.schedule_block.member_not_found');
                }
            }

            $updated = ScheduleBlock::reconstitute(
                $block->id,
                $block->businessId,
                $member,
                $changes['starts_at'] ?? $block->startsAt,
                $changes['ends_at'] ?? $block->endsAt,
                $changes['reason'] ?? $block->reason,
                $block->origin,
            );

            $this->blocks->save($updated);

            return $updated;
        });
    }
}
