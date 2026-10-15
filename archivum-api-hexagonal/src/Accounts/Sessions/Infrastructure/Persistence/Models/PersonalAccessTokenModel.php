<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum access token whose key is a UUID version 7, like every table of the project.
 */
#[Table('personal_access_tokens')]
final class PersonalAccessTokenModel extends PersonalAccessToken
{
    use HasUuids;
}
