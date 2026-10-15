<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Persistence\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 */
#[Table('users')]
#[Fillable(['id', 'name', 'email', 'password'])]
#[Hidden(['password'])]
#[UseFactory(UserFactory::class)]
final class UserModel extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids;
}
