<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Persistence\Models;

use Database\Factories\PerfumeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $business_id
 * @property string $brand
 * @property string $name
 * @property string|null $concentration
 * @property string|null $gender
 * @property string|null $family
 * @property string|null $description
 * @property string|null $image_path
 * @property bool $is_active
 */
#[Table('perfumes')]
#[Fillable(['id', 'business_id', 'brand', 'name', 'concentration', 'gender', 'family', 'description', 'image_path', 'is_active'])]
#[UseFactory(PerfumeFactory::class)]
final class PerfumeModel extends Model
{
    /** @use HasFactory<PerfumeFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the boolean column to a native type.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<PerfumeNoteModel, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(PerfumeNoteModel::class, 'perfume_id');
    }

    /** @return HasMany<PerfumeTagModel, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(PerfumeTagModel::class, 'perfume_id');
    }

    /** @return HasMany<PerfumeVariantModel, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(PerfumeVariantModel::class, 'perfume_id');
    }
}
