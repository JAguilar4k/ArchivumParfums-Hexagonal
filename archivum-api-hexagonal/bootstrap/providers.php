<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Accounts\Sessions\Infrastructure\SessionsServiceProvider;
use Src\Accounts\Users\Infrastructure\UsersServiceProvider;
use Src\Businesses\Logo\Infrastructure\LogoServiceProvider;
use Src\Businesses\Members\Infrastructure\MembersServiceProvider;
use Src\Businesses\Profile\Infrastructure\ProfileEventsServiceProvider;
use Src\Businesses\Profile\Infrastructure\ProfileServiceProvider;
use Src\Businesses\PublicProfile\Infrastructure\PublicProfileServiceProvider;
use Src\Catalog\Perfumes\Infrastructure\PerfumesServiceProvider;
use Src\Catalog\Showcase\Infrastructure\ShowcaseServiceProvider;
use Src\Decants\Bottles\Infrastructure\BottlesServiceProvider;
use Src\Payments\Payments\Infrastructure\PaymentsServiceProvider;
use Src\Sales\Orders\Infrastructure\OrdersServiceProvider;
use Src\Shared\Infrastructure\RateLimitsServiceProvider;
use Src\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    RateLimitsServiceProvider::class,
    MembersServiceProvider::class,
    ProfileServiceProvider::class,
    ProfileEventsServiceProvider::class,
    LogoServiceProvider::class,
    PublicProfileServiceProvider::class,
    PerfumesServiceProvider::class,
    ShowcaseServiceProvider::class,
    SessionsServiceProvider::class,
    UsersServiceProvider::class,
    BottlesServiceProvider::class,
    OrdersServiceProvider::class,
    PaymentsServiceProvider::class,
];
