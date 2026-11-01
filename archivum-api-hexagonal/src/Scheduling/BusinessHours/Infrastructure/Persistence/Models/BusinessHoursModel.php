<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models;

use Database\Factories\BusinessHoursModelFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(BusinessHoursModelFactory::class)]
class BusinessHoursModel extends Model
{
    /** @use HasFactory<BusinessHoursModelFactory> */
    use HasFactory, HasUuids;

    protected $table = 'business_hours';

    protected $fillable = ['id', 'business_id', 'weekday', 'opens_at', 'closes_at'];

    protected $casts = ['weekday' => 'integer'];

    /**
     * @return Factory<BusinessHoursModel>
     */
    protected static function newFactory(): Factory
    {
        return BusinessHoursModelFactory::new();
    }
}
