<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Infrastructure\Persistence\Models;

use Database\Factories\ScheduleBlockModelFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(ScheduleBlockModelFactory::class)]
class ScheduleBlockModel extends Model
{
    /** @use HasFactory<ScheduleBlockModelFactory> */
    use HasFactory, HasUuids;

    protected $table = 'schedule_blocks';

    protected $fillable = ['id', 'business_id', 'member_id', 'starts_at', 'ends_at', 'reason', 'source', 'external_id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    /**
     * @return Factory<ScheduleBlockModel>
     */
    protected static function newFactory(): Factory
    {
        return ScheduleBlockModelFactory::new();
    }
}
