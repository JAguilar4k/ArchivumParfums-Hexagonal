<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $business_id
 * @property string $perfume_id
 * @property string $kind
 * @property string $value
 */
#[Table('perfume_tags')]
#[Fillable(['id', 'business_id', 'perfume_id', 'kind', 'value'])]
final class PerfumeTagModel extends Model
{
    use HasUuids;
}
