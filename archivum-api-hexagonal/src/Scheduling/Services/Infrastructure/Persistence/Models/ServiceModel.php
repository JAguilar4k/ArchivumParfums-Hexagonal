<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Persistence\Models;

use Database\Factories\ServiceModelFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(ServiceModelFactory::class)]
class ServiceModel extends Model
{
    /** @use HasFactory<ServiceModelFactory> */
    use HasFactory, HasUuids;

    protected $table = 'services';

    protected $fillable = ['id', 'business_id', 'name', 'duration_minutes', 'price', 'buffer_minutes', 'max_perfumes', 'is_active'];

    protected $casts = ['duration_minutes' => 'integer', 'price' => 'integer', 'buffer_minutes' => 'integer', 'max_perfumes' => 'integer', 'is_active' => 'boolean'];

    /**
     * @return Factory<ServiceModel>
     */
    protected static function newFactory(): Factory
    {
        return ServiceModelFactory::new();
    }
}
