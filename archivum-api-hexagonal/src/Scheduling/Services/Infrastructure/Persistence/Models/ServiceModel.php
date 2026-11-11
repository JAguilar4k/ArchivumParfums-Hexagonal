<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Persistence\Models;

use Database\Factories\ServiceModelFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A service of one business exactly as the table stores it: the columns are named in English, as every column of the
 * database, and the API names them in Spanish through its resource.
 *
 * @property string $id
 * @property string $business_id
 * @property string $name
 * @property int $duration_minutes
 * @property int $price
 * @property int $buffer_minutes
 * @property int $max_perfumes
 * @property bool $is_active
 */
#[UseFactory(ServiceModelFactory::class)]
final class ServiceModel extends Model
{
    /** @use HasFactory<ServiceModelFactory> */
    use HasFactory, HasUuids;

    /** @var string */
    protected $table = 'services';

    /** @var list<string> */
    protected $fillable = ['id', 'business_id', 'name', 'duration_minutes', 'price', 'buffer_minutes', 'max_perfumes', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'integer',
            'buffer_minutes' => 'integer',
            'max_perfumes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<ServiceModel>
     */
    protected static function newFactory(): Factory
    {
        return ServiceModelFactory::new();
    }
}
