<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Infrastructure\Persistence\Models;

use Database\Factories\StaffScheduleModelFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(StaffScheduleModelFactory::class)]
class StaffScheduleModel extends Model
{
    /** @use HasFactory<StaffScheduleModelFactory> */
    use HasFactory, HasUuids;

    protected $table = 'staff_schedules';

    protected $fillable = ['id', 'business_id', 'member_id', 'weekday', 'starts_at', 'ends_at'];

    protected $casts = ['weekday' => 'integer'];

    /**
     * @return Factory<StaffScheduleModel>
     */
    protected static function newFactory(): Factory
    {
        return StaffScheduleModelFactory::new();
    }
}
