<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Src\Businesses\Profile\Domain\Events\BusinessMarginChanged;
use Src\Catalog\Perfumes\Application\Contracts\BottleMargin;
use Src\Catalog\Perfumes\Application\Contracts\PerfumeSearch;
use Src\Catalog\Perfumes\Application\Contracts\PerfumeVariantPrices;
use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Domain\Contracts\VariantCost;
use Src\Catalog\Perfumes\Infrastructure\Listeners\RecalculateSalePricesOnMarginChange;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Repositories\EloquentPerfumeRepository;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Repositories\EloquentPerfumeVariantPrices;
use Src\Catalog\Perfumes\Infrastructure\Pricing\BusinessSettingsBottleMargin;
use Src\Catalog\Perfumes\Infrastructure\Pricing\NoSupplierCost;

final class PerfumesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PerfumeRepository::class => EloquentPerfumeRepository::class,
        PerfumeSearch::class => EloquentPerfumeRepository::class,
        PerfumeVariantPrices::class => EloquentPerfumeVariantPrices::class,
        VariantCost::class => NoSupplierCost::class,
        BottleMargin::class => BusinessSettingsBottleMargin::class,
    ];

    /** Listen to the change of the margin of a business, announced by the Businesses module. */
    public function boot(): void
    {
        Event::listen(BusinessMarginChanged::class, RecalculateSalePricesOnMarginChange::class);
    }
}
