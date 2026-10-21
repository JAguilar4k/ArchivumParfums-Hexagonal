<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model of a tag for visitors.
 *
 * @property string $id
 * @property string $perfume_id
 * @property string $kind
 * @property string $value
 */
final class ShowcaseTagModel extends Model
{
    protected $table = 'perfume_tags';

    protected $keyType = 'string';

    public $incrementing = false;
}
