<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

trait ScopesQueriesToBusiness
{
    /** Make the next queries of the test reach only the rows of a business, until something scopes them again. */
    protected function scopeQueriesTo(?string $businessId): void
    {
        $this->app->make(BusinessTransaction::class)->scope($businessId);
    }
}
