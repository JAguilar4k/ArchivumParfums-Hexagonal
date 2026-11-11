<?php

declare(strict_types=1);

return [

    /*
    | Store used when none is named. The database store keeps the cache in the "cache" table of PostgreSQL, shared
    | by every process of the application: the limits of requests (#108) and the locks of the unique jobs (#156).
    | The tests use the array store unless they ask for the database one.
    */
    'default' => env('CACHE_STORE', 'database'),

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        /*
        | The locks go through a connection of their own. Laravel takes a lock with an INSERT and, when the lock is
        | already held, answers false after the INSERT fails; PostgreSQL aborts the whole transaction on that error.
        | A unique job dispatched again inside the transaction of an operation would make the operation fail, so the
        | lock must never share the connection of that transaction.
        */
        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'cache',
            'lock_connection' => 'pgsql_locks',
            'lock_table' => 'cache_locks',
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

    ],

    /*
    | Prefix of every key, so that another application sharing the store cannot collide with this one.
    */
    'prefix' => 'archivum-cache-',

    /*
    | Classes an item read from the cache may be unserialized into. With false, only scalars and arrays come back:
    | the application keeps only counters there.
    */
    'serializable_classes' => false,

];
