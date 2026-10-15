<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Contracts;

interface TransactionRunner
{
    /**
     * Run the callback in a transaction that is rolled back when it throws.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
