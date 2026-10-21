<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ServiceModel extends Model
{
    use HasUuids;

    protected $table = 'services';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'duration' => 'integer',
        'price' => 'integer',
        'margin' => 'integer',
        'max_perfumes' => 'integer',
        'is_active' => 'boolean',
    ];
}
