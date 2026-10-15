<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Closure;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Run operations at the same time, each in its own PHP process with its own connection to PostgreSQL.
 *
 * The transaction that wraps each test hides its rows from every other connection, so a test of operations
 * that compete for the same row commits what they share with the schema owner, DB::connection('pgsql_admin'),
 * and lets each operation commit from its own process. inParallel() in tests/Pest.php uses this class.
 */
final class ParallelProcesses
{
    /** Seconds every process gets to boot Laravel before all of them start their operation at once. */
    private const float BOOT_SECONDS = 1.5;

    /**
     * Run every operation in its own process and start them at the same instant.
     * Return their results in the order of the operations.
     *
     * @param  list<Closure>  $operations
     * @return list<mixed>
     */
    public static function run(array $operations): array
    {
        $startAt = microtime(true) + self::BOOT_SECONDS;

        $tasks = array_map(fn (Closure $operation): Closure => self::startingAt($startAt, $operation), $operations);

        return array_values(Concurrency::driver('process')->run($tasks));
    }

    /**
     * Empty the tables with the schema owner. The test database keeps no other committed rows,
     * because every other test rolls its transaction back.
     *
     * @param  list<string>  $tables
     */
    public static function emptyTables(array $tables): void
    {
        $owner = DB::connection('pgsql_admin');
        $names = array_map(fn (string $table): string => $owner->getQueryGrammar()->wrapTable($table), $tables);

        $owner->statement('truncate table '.implode(', ', $names).' cascade');
    }

    /** Wrap the operation so that it waits for the start instant before it runs. */
    private static function startingAt(float $startAt, Closure $operation): Closure
    {
        // The process cannot load the class of the test that wrote the operation, so the operation travels without it.
        $operation = Closure::bind($operation, null, null)
            ?? throw new LogicException('An operation that runs in parallel cannot use $this.');

        return static function () use ($startAt, $operation): mixed {
            $wait = $startAt - microtime(true);

            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }

            return $operation();
        };
    }
}
