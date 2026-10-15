<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Accounts\Users\Domain\Contracts\CustomerBusinesses;
use Src\Accounts\Users\Domain\Contracts\PasswordHasher;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Infrastructure\Persistence\Repositories\EloquentUserRepository;
use Src\Accounts\Users\Infrastructure\Persistence\Repositories\PostgresCustomerBusinesses;
use Src\Accounts\Users\Infrastructure\Security\BcryptPasswordHasher;
use Src\Accounts\Users\Infrastructure\Tenancy\CustomerBusinessContext;
use Src\Shared\Infrastructure\Tenancy\ScopeRequestToBusiness;

final class UsersServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CustomerBusinesses::class => PostgresCustomerBusinesses::class,
        PasswordHasher::class => BcryptPasswordHasher::class,
        UserRepository::class => EloquentUserRepository::class,
    ];

    /** Offer the business of a resource of the customer to the routes of /api/v1/cuenta. */
    public function register(): void
    {
        $this->app->bind(ScopeRequestToBusiness::contextFor(ScopeRequestToBusiness::ACCOUNT), CustomerBusinessContext::class);
    }
}
