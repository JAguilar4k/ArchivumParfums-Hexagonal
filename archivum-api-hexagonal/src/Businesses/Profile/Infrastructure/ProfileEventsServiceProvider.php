<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Observers\BusinessMarginObserver;

final class ProfileEventsServiceProvider extends ServiceProvider
{
    /** Start announcing the changes of the margin of a business. */
    public function boot(): void
    {
        BusinessModel::observe(BusinessMarginObserver::class);
    }
}
