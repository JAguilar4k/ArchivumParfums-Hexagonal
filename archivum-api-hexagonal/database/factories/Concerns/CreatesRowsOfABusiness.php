<?php

declare(strict_types=1);

namespace Database\Factories\Concerns;

use Illuminate\Database\Eloquent\Model;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

trait CreatesRowsOfABusiness
{
    /** Pin the open transaction to the business of each row before inserting it, as row-level security demands. */
    public function configure(): static
    {
        return $this->afterMaking(function (Model $model): void {
            app(BusinessTransaction::class)->scope((string) $model->getAttribute('business_id'));
        });
    }
}
