<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Application\UseCases;

use Src\Scheduling\ScheduleBlocks\Application\DTOs\CreateScheduleBlockCommand;
use Src\Scheduling\ScheduleBlocks\Domain\Contracts\ScheduleBlockRepository;
use Src\Scheduling\ScheduleBlocks\Domain\Entities\ScheduleBlock;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\BlockOrigin;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * Block a range of the agenda of the business of the session, for one person or for the whole store. A person of another
 * business is refused as an invalid value, because the body receives her identifier.
 */
final readonly class CreateScheduleBlock
{
    /** Receive the repository, the business of the session and the transaction ports. */
    public function __construct(
        private ScheduleBlockRepository $blocks,
        private BusinessContext $businessContext,
        private TransactionRunner $transaction,
    ) {}

    /** Build the block with the values of the command and store it. */
    public function execute(CreateScheduleBlockCommand $command): ScheduleBlock
    {
        return $this->transaction->run(function () use ($command): ScheduleBlock {
            $member = $command->memberId === null ? null : new MemberId($command->memberId);

            if ($member !== null && ! $this->blocks->memberExists($member)) {
                throw new InvalidValueException('scheduling.schedule_block.member_not_found');
            }

            $block = ScheduleBlock::create(
                $this->blocks->nextId(),
                $this->businessContext->current(),
                $member,
                $command->startsAt,
                $command->endsAt,
                $command->reason,
                BlockOrigin::MANUAL,
            );

            $this->blocks->save($block);

            return $block;
        });
    }
}
