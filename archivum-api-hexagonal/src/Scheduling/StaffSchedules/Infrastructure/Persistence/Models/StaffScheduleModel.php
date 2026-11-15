<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Infrastructure\Persistence\Models;

use Database\Factories\StaffScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $business_id
 * @property string $member_id
 * @property int $weekday
 * @property string $starts_at
 * @property string $ends_at
 */
#[Table('staff_schedules')]
#[Fillable(['id', 'business_id', 'member_id', 'weekday', 'starts_at', 'ends_at'])]
#[UseFactory(StaffScheduleFactory::class)]
final class StaffScheduleModel extends Model
{
    /** @use HasFactory<StaffScheduleFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the weekday to a native integer. The local hours stay as the text PostgreSQL returns for a time.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }
}
