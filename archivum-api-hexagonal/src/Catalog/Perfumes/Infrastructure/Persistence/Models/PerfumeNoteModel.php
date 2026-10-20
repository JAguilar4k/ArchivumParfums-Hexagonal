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
 * @property string $layer
 * @property string $note
 */
#[Table('perfume_notes')]
#[Fillable(['id', 'business_id', 'perfume_id', 'layer', 'note'])]
final class PerfumeNoteModel extends Model
{
    use HasUuids;
}
