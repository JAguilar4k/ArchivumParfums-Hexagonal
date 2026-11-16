<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Infrastructure\Persistence\Models;

use Database\Factories\ScheduleBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $business_id
 * @property string|null $member_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $reason
 * @property string $source
 * @property string|null $external_id
 */
#[Table('schedule_blocks')]
#[Fillable(['id', 'business_id', 'member_id', 'starts_at', 'ends_at', 'reason', 'source', 'external_id'])]
#[UseFactory(ScheduleBlockFactory::class)]
final class ScheduleBlockModel extends Model
{
    /** @use HasFactory<ScheduleBlockFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the two instants of the block to dates.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
