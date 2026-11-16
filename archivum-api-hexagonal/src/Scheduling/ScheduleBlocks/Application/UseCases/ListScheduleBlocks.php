<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Application\UseCases;

use Src\Scheduling\ScheduleBlocks\Application\DTOs\ScheduleBlockFilters;
use Src\Scheduling\ScheduleBlocks\Domain\Contracts\ScheduleBlockRepository;
use Src\Scheduling\ScheduleBlocks\Domain\Entities\ScheduleBlock;
use Src\Shared\Domain\ValueObjects\MemberId;

/** List the blocks of the business of the session that cross a range, of one person when the filter says so. */
final readonly class ListScheduleBlocks
{
    /** Receive the repository. */
    public function __construct(private ScheduleBlockRepository $blocks) {}

    /**
     * The blocks that cross the range, oldest first.
     *
     * @return list<ScheduleBlock>
     */
    public function execute(ScheduleBlockFilters $filters): array
    {
        return $this->blocks->crossing(
            $filters->from,
            $filters->to,
            $filters->memberId === null ? null : new MemberId($filters->memberId),
        );
    }
}
