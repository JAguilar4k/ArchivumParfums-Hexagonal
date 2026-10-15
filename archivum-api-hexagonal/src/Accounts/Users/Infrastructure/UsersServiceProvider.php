<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Accounts\Users\Domain\Contracts\PasswordHasher;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Infrastructure\Persistence\Repositories\EloquentUserRepository;
use Src\Accounts\Users\Infrastructure\Security\BcryptPasswordHasher;

final class UsersServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PasswordHasher::class => BcryptPasswordHasher::class,
        UserRepository::class => EloquentUserRepository::class,
    ];
}
