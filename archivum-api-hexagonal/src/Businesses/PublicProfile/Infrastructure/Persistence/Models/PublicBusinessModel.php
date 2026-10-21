<?php

declare(strict_types=1);

namespace Src\Businesses\PublicProfile\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model of a business for visitors: it maps only the public columns of the table, so nothing else can reach a response.
 *
 * @property string $name
 * @property string $slug
 * @property string|null $logo_path
 * @property string|null $location_text
 * @property string $timezone
 */
final class PublicBusinessModel extends Model
{
    protected $table = 'businesses';

    /**
     * Columns that the read model selects.
     *
     * @var list<string>
     */
    public const array COLUMNS = ['id', 'name', 'slug', 'logo_path', 'location_text', 'timezone'];
}
