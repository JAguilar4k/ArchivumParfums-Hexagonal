<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Domain\Contracts\TransactionRunner;
use Throwable;

final class RecordingTransactionRunner implements TransactionRunner
{
    private int $runs = 0;

    private int $committed = 0;

    private int $rolledBack = 0;

    /** Run the callback and record whether it committed or rolled back. */
    public function run(callable $callback): mixed
    {
        $this->runs++;

        try {
            $result = $callback();
        } catch (Throwable $exception) {
            $this->rolledBack++;

            throw $exception;
        }

        $this->committed++;

        return $result;
    }

    /** Count the transactions opened. */
    public function runs(): int
    {
        return $this->runs;
    }

    /** Count the transactions that finished without an error. */
    public function committed(): int
    {
        return $this->committed;
    }

    /** Count the transactions that ended with an error. */
    public function rolledBack(): int
    {
        return $this->rolledBack;
    }
}
