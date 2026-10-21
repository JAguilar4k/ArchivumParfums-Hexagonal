<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Accounts\Sessions\Infrastructure\SessionsServiceProvider;
use Src\Accounts\Users\Infrastructure\UsersServiceProvider;
use Src\Businesses\Logo\Infrastructure\LogoServiceProvider;
use Src\Businesses\Members\Infrastructure\MembersServiceProvider;
use Src\Businesses\Profile\Infrastructure\ProfileEventsServiceProvider;
use Src\Businesses\Profile\Infrastructure\ProfileServiceProvider;
use Src\Catalog\Perfumes\Infrastructure\PerfumesServiceProvider;
use Src\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    MembersServiceProvider::class,
    ProfileServiceProvider::class,
    ProfileEventsServiceProvider::class,
    LogoServiceProvider::class,
    PerfumesServiceProvider::class,
    SessionsServiceProvider::class,
    UsersServiceProvider::class,
];
