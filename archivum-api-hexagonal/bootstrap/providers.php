<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Accounts\Users\Infrastructure\UsersServiceProvider;
use Src\Businesses\Members\Infrastructure\MembersServiceProvider;
use Src\Businesses\Profile\Infrastructure\ProfileServiceProvider;
use Src\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    MembersServiceProvider::class,
    ProfileServiceProvider::class,
    UsersServiceProvider::class,
];
