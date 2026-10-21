<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model of a note for visitors.
 *
 * @property string $id
 * @property string $perfume_id
 * @property string $layer
 * @property string $note
 */
final class ShowcaseNoteModel extends Model
{
    protected $table = 'perfume_notes';

    protected $keyType = 'string';

    public $incrementing = false;
}
