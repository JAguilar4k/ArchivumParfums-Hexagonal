<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Persistence;

use Closure;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final readonly class PaymentTransactionRunner implements TransactionRunner
{
    public function __construct(private BusinessContext $business, private BusinessTransaction $transaction) {}

    public function run(callable $callback): mixed
    {
        return $this->transaction->run($this->business->current()->value, Closure::fromCallable($callback));
    }
}
