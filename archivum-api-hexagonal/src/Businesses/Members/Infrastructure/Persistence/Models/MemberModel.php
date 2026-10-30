<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Persistence\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $business_id
 * @property string $user_id
 * @property string $role
 * @property bool $attends_appointments
 * @property bool $is_active
 */
#[Table('members')]
#[Fillable(['id', 'business_id', 'user_id', 'role', 'attends_appointments', 'is_active'])]
#[UseFactory(MemberFactory::class)]
final class MemberModel extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the boolean columns to a native type.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attends_appointments' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
