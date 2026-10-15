<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Sessions\Application\Contracts\StaffMemberships;
use Src\Accounts\Sessions\Domain\Contracts\TokenIssuer;
use Src\Accounts\Sessions\Domain\Contracts\TokenRevoker;
use Src\Accounts\Sessions\Infrastructure\Businesses\BusinessesStaffMemberships;
use Src\Accounts\Sessions\Infrastructure\Persistence\Models\PersonalAccessTokenModel;
use Src\Accounts\Sessions\Infrastructure\Security\SanctumTokenIssuer;
use Src\Accounts\Sessions\Infrastructure\Security\SanctumTokenRevoker;

final class SessionsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        StaffMemberships::class => BusinessesStaffMemberships::class,
        TokenIssuer::class => SanctumTokenIssuer::class,
        TokenRevoker::class => SanctumTokenRevoker::class,
    ];

    /** Store the Sanctum tokens with UUID keys. */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessTokenModel::class);
    }
}
