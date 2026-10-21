<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BusinessHourModel extends Model
{
    use HasUuids;

    protected $table = 'business_hours';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'weekday' => 'integer',
    ];
}