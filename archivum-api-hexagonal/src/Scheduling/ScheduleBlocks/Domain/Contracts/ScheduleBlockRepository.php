<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Domain\Contracts;

use DateTimeImmutable;
use Src\Scheduling\ScheduleBlocks\Domain\Entities\ScheduleBlock;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\ScheduleBlockId;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * Port of the blocks of the agenda of one business. Every query reaches only the rows of the business of the context, and
 * the row-level policy of PostgreSQL is the second barrier.
 */
interface ScheduleBlockRepository
{
    /** Generate a time-ordered identity for a new block; the adapter owns the generation, the application does not. */
    public function nextId(): ScheduleBlockId;

    /** Write the block of the business of the context. */
    public function save(ScheduleBlock $block): void;

    /** Find a block of the business of the context; one of another business is never reached. */
    public function find(ScheduleBlockId $id): ?ScheduleBlock;

    /** Delete the block of the business of the context. */
    public function delete(ScheduleBlock $block): void;

    /**
     * The blocks of the business of the context that cross the range, oldest first, of one person when a member is given.
     *
     * @return list<ScheduleBlock>
     */
    public function crossing(DateTimeImmutable $from, DateTimeImmutable $to, ?MemberId $member): array;

    /** Whether that person belongs to the staff of this business; a person of another business is unknown here. */
    public function memberExists(MemberId $member): bool;
}
