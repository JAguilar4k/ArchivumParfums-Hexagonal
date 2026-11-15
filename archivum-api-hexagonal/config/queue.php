<?php

declare(strict_types=1);

return [

    /*
    | Connection used when none is named. The database connection keeps each job in the "jobs" table of PostgreSQL
    | until the worker of php artisan queue:work runs it (#156); the tests use the sync connection unless they ask
    | for the database one.
    */
    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        /*
        | A job dispatched inside a transaction is written only after that transaction commits: if it rolls back,
        | the job never exists.
        */
        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => true,
        ],

    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'job_batches',
    ],

    /*
    | A job that runs out of attempts is kept in "failed_jobs" with its exception and its own identifier, from
    | where php artisan queue:retry sends it to the queue again.
    */
    'failed' => [
        'driver' => 'database-uuids',
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],

];
