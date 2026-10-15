<?php

declare(strict_types=1);

use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;

return [

    'defaults' => [
        'guard' => 'sanctum',
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

];
