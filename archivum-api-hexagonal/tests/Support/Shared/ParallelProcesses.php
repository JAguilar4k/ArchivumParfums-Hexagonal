<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Closure;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/**
 * Run operations at the same time, each in its own PHP process with its own connection to PostgreSQL.
 *
 * The transaction that wraps each test hides its rows from every other connection, so a test of operations
 * that compete for the same row commits what they share with the schema owner, DB::connection('pgsql_admin'),
 * and lets each operation commit from its own process. inParallel() in tests/Pest.php uses this class.
 */
final class ParallelProcesses
{
    /** Maximum time a booted process waits for the other processes to become ready. */
    private const int READY_TIMEOUT_SECONDS = 10;

    /**
     * Run every operation in its own process, after every process has finished booting.
     * Return their results in the order of the operations.
     *
     * @param  list<Closure>  $operations
     * @return list<mixed>
     */
    public static function run(array $operations): array
    {
        if ($operations === []) {
            return [];
        }

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'archivum-parallel-'.bin2hex(random_bytes(12));

        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Could not create the parallel process barrier.');
        }

        try {
            $tasks = array_map(
                fn (Closure $operation, int $index): Closure => self::waitingForAll($directory, $index, count($operations), $operation),
                $operations,
                array_keys($operations),
            );

            return array_values(Concurrency::driver('process')->run($tasks));
        } finally {
            foreach (glob($directory.DIRECTORY_SEPARATOR.'*.ready') ?: [] as $marker) {
                unlink($marker);
            }

            rmdir($directory);
        }
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

    /** Wrap the operation so that every process signals its readiness and waits for all its peers. */
    private static function waitingForAll(string $directory, int $index, int $processes, Closure $operation): Closure
    {
        // The process cannot load the class of the test that wrote the operation, so the operation travels without it.
        $operation = Closure::bind($operation, null, null)
            ?? throw new LogicException('An operation that runs in parallel cannot use $this.');

        $timeout = self::READY_TIMEOUT_SECONDS;

        return static function () use ($directory, $index, $processes, $timeout, $operation): mixed {
            if (! touch($directory.DIRECTORY_SEPARATOR.$index.'.ready')) {
                throw new RuntimeException('Could not signal that a parallel process is ready.');
            }

            $deadline = microtime(true) + $timeout;

            while (count(glob($directory.DIRECTORY_SEPARATOR.'*.ready') ?: []) !== $processes) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Timed out waiting for all parallel processes to become ready.');
                }

                usleep(10_000);
            }

            return $operation();
        };
    }
}
