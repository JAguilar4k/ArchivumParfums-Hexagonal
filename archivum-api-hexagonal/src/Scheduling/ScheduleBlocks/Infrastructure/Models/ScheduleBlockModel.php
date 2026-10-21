<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ScheduleBlockModel extends Model
{
    use HasUuids;

    protected $table = 'schedule_blocks';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}