<?php

declare(strict_types=1);

use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;

return [

    'defaults' => [
        'guard' => 'sanctum',
        'passwords' => 'users',
    ],

    'guards' => [
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => UserModel::class,
        ],
    ],

    // The broker of the password recovery (#193): the link lasts these minutes, and one account gets one per minute.
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => (int) env('ARCHIVUM_RESET_LINK_MINUTES', 60),
            'throttle' => 60,
        ],
    ],

];
