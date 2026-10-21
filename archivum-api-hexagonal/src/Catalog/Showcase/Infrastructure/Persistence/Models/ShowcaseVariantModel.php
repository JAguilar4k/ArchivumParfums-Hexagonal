<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model of a presentation for visitors.
 *
 * @property string $id
 * @property string $perfume_id
 * @property int $size_ml
 * @property int|null $sale_price
 * @property bool $is_active
 */
final class ShowcaseVariantModel extends Model
{
    protected $table = 'perfume_variants';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Cast the columns to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size_ml' => 'integer', 'sale_price' => 'integer', 'is_active' => 'boolean'];
    }
}
