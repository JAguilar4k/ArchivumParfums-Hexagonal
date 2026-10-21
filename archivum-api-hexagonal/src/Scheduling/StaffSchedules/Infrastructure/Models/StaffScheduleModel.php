<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StaffScheduleModel extends Model
{
    use HasUuids;

    protected $table = 'staff_schedules';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'weekday' => 'integer',
    ];
}
