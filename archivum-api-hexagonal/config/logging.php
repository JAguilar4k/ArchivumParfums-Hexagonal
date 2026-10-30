<?php

declare(strict_types=1);

use Src\Shared\Infrastructure\Logging\RedactSensitiveData;

/*
 * The channels that write a log, each one with the redaction of personal data (#110). Everything else of the logging
 * configuration, such as the stack channel, comes from the defaults of the framework.
 */
return [

    'channels' => [

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
            'tap' => [RedactSensitiveData::class],
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
            'tap' => [RedactSensitiveData::class],
        ],

    ],

];
