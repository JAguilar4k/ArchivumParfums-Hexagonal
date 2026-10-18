<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Persistence\Models;

use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_path
 * @property string $timezone
 * @property string|null $location_text
 * @property array<string, mixed> $settings
 */
#[Table('businesses')]
#[Fillable(['id', 'name', 'slug', 'logo_path', 'timezone', 'location_text', 'settings'])]
#[UseFactory(BusinessFactory::class)]
final class BusinessModel extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the settings column to an array.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
