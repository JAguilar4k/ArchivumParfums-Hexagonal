<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Domain\Entities;

use DateTimeImmutable;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\BlockOrigin;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\ScheduleBlockId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

final class ScheduleBlock
{
    private function __construct(
        public readonly ScheduleBlockId $id,
        public readonly BusinessId $businessId,
        public readonly ?MemberId $memberId, // Nullable: if null, it blocks the whole business
        public readonly DateTimeImmutable $startsAt,
        public readonly DateTimeImmutable $endsAt,
        public readonly string $reason,
        public readonly BlockOrigin $origin
    ) {
        // P2: Use domain exceptions, not generic ones
        if ($this->endsAt <= $this->startsAt) {
            throw new InvalidValueException('scheduling.schedule_block.invalid_order');
        }

        if (trim($this->reason) === '') {
            throw new InvalidValueException('scheduling.block.reason_required');
        }
    }

    public static function create(
        ScheduleBlockId $id,
        BusinessId $businessId,
        ?MemberId $memberId,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        string $reason,
        BlockOrigin $origin
    ): self {
        return new self($id, $businessId, $memberId, $startsAt, $endsAt, $reason, $origin);
    }

    /** Restore the persisted values while keeping the constructor invariants. */
    public static function reconstitute(
        ScheduleBlockId $id,
        BusinessId $businessId,
        ?MemberId $memberId,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        string $reason,
        BlockOrigin $origin
    ): self {
        return new self($id, $businessId, $memberId, $startsAt, $endsAt, $reason, $origin);
    }
}
