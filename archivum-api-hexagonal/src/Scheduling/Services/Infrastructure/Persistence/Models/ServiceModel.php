<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Persistence\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $business_id
 * @property string $name
 * @property int $duration_minutes
 * @property int $price
 * @property int $buffer_minutes
 * @property int $max_perfumes
 * @property bool $is_active
 */
#[Table('services')]
#[Fillable(['id', 'business_id', 'name', 'duration_minutes', 'price', 'buffer_minutes', 'max_perfumes', 'is_active'])]
#[UseFactory(ServiceFactory::class)]
final class ServiceModel extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the integer and boolean columns to native types.
     *
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
}
