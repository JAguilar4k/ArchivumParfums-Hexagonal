<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class DatabaseTransactionRunner implements TransactionRunner
{
    /**
     * Run the callback inside a database transaction.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed
    {
        return DB::transaction(fn (): mixed => $callback());
    }
}
