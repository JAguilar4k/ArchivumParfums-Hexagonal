<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Infrastructure\Persistence\DatabaseTransactionRunner;
use Src\Shared\Infrastructure\Tenancy\UnresolvedBusinessContext;

final class SharedServiceProvider extends ServiceProvider
{
    /** Bind the shared ports to their adapters. */
    public function register(): void
    {
        $this->app->bind(BusinessContext::class, UnresolvedBusinessContext::class);
        $this->app->bind(TransactionRunner::class, DatabaseTransactionRunner::class);
    }
}
