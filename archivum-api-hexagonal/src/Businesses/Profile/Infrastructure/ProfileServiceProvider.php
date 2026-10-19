<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Businesses\Profile\Application\Contracts\OwnerAccounts;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Infrastructure\Accounts\AccountsOwnerAccounts;
use Src\Businesses\Profile\Infrastructure\Console\CreateBusinessConsoleCommand;
use Src\Businesses\Profile\Infrastructure\Persistence\Repositories\EloquentBusinessRepository;

final class ProfileServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        BusinessRepository::class => EloquentBusinessRepository::class,
        OwnerAccounts::class => AccountsOwnerAccounts::class,
    ];

    /** Register the console command of the module. */
    public function boot(): void
    {
        $this->commands([CreateBusinessConsoleCommand::class]);
    }
}
