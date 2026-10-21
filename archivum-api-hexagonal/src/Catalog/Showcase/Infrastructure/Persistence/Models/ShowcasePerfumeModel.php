<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Read model of a perfume for visitors. The one rule of what a visitor may see lives in scopeVisible and in the relation
 * of the sellable presentations, so the list and the detail cannot disagree.
 *
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
final class ShowcasePerfumeModel extends Model
{
    protected $table = 'perfumes';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Cast the boolean column to a native type.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Only the perfumes that can be shown: active and with at least one active presentation that has a sale price.
     *
     * @param  Builder<ShowcasePerfumeModel>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)->whereExists(
            fn (QueryBuilder $variants) => $variants->selectRaw('1')
                ->from('perfume_variants as sellable')
                ->whereColumn('sellable.perfume_id', 'perfumes.id')
                ->whereColumn('sellable.business_id', 'perfumes.business_id')
                ->where('sellable.is_active', true)
                ->whereNotNull('sellable.sale_price'),
        );
    }

    /** @return HasMany<ShowcaseVariantModel, $this> */
    public function sellableVariants(): HasMany
    {
        return $this->hasMany(ShowcaseVariantModel::class, 'perfume_id')
            ->where('is_active', true)
            ->whereNotNull('sale_price')
            ->orderBy('size_ml');
    }

    /** @return HasMany<ShowcaseNoteModel, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ShowcaseNoteModel::class, 'perfume_id');
    }

    /** @return HasMany<ShowcaseTagModel, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(ShowcaseTagModel::class, 'perfume_id');
    }
}
