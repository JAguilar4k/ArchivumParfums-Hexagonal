<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum access token whose key is a UUID version 7, like every table of the project.
 *
 * Not final, unlike the other models: Sanctum::actingAs() mocks this class in the tests.
 */
#[Table('personal_access_tokens')]
class PersonalAccessTokenModel extends PersonalAccessToken
{
    use HasUuids;
}
