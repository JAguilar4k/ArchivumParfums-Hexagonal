<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Scheduling\ScheduleBlocks\Domain\Contracts\ScheduleBlockRepository;
use Src\Scheduling\ScheduleBlocks\Domain\Entities\ScheduleBlock;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\BlockOrigin;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\ScheduleBlockId;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Persistence\Models\ScheduleBlockModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

final readonly class EloquentScheduleBlockRepository implements ScheduleBlockRepository
{
    /** Take the business that every query of the repository filters by. */
    public function __construct(private BusinessContext $context) {}

    /** Generate a time-ordered UUID for a new block. */
    public function nextId(): ScheduleBlockId
    {
        return new ScheduleBlockId((string) Str::uuid7());
    }

    /** Write the block in the business of the context; the source is never changed here. */
    public function save(ScheduleBlock $block): void
    {
        $business = $this->context->current();

        ScheduleBlockModel::query()->updateOrCreate(
            ['id' => $block->id->value, 'business_id' => $business->value],
            [
                'member_id' => $block->memberId?->value,
                'starts_at' => $this->stored($block->startsAt),
                'ends_at' => $this->stored($block->endsAt),
                'reason' => $block->reason,
                'source' => $block->origin->value,
            ],
        );
    }

    /** Find a block of the business of the context; one of another business is never reached. */
    public function find(ScheduleBlockId $id): ?ScheduleBlock
    {
        $model = ScheduleBlockModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('id', $id->value)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** Delete the block of the business of the context. */
    public function delete(ScheduleBlock $block): void
    {
        ScheduleBlockModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('id', $block->id->value)
            ->delete();
    }

    /** The blocks of the business of the context that cross the range, of that person when she is given. */
    public function crossing(DateTimeImmutable $from, DateTimeImmutable $to, ?MemberId $member): array
    {
        $query = ScheduleBlockModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('starts_at', '<', $to->format('Y-m-d H:i:sP'))
            ->where('ends_at', '>', $from->format('Y-m-d H:i:sP'))
            ->orderBy('starts_at');

        if ($member !== null) {
            $query->where('member_id', $member->value);
        }

        return array_values($query->get()->map(fn (ScheduleBlockModel $model): ScheduleBlock => $this->toDomain($model))->all());
    }

    /** Whether that person belongs to the staff of this business. */
    public function memberExists(MemberId $member): bool
    {
        return MemberModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('id', $member->value)
            ->exists();
    }

    /**
     * The instant in the time zone of the connection. Eloquent writes a date as its wall time without the offset and
     * PostgreSQL reads that wall time in the zone of the connection, so a block sent with another offset, such as Z,
     * would be stored hours away from itself.
     */
    private function stored(DateTimeImmutable $instant): DateTimeImmutable
    {
        return $instant->setTimezone(new DateTimeZone((string) config('database.connections.pgsql.timezone', 'UTC')));
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(ScheduleBlockModel $model): ScheduleBlock
    {
        return ScheduleBlock::reconstitute(
            new ScheduleBlockId($model->id),
            new BusinessId($model->business_id),
            $model->member_id === null ? null : new MemberId($model->member_id),
            new DateTimeImmutable((string) $model->starts_at),
            new DateTimeImmutable((string) $model->ends_at),
            $model->reason,
            BlockOrigin::from($model->source),
        );
    }
}
