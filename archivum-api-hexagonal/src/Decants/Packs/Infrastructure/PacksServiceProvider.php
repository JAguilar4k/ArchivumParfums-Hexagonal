<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Decants\Packs\Application\Contracts\DecantSizes;
use Src\Decants\Packs\Application\Contracts\PublicPackPlanReader;
use Src\Decants\Packs\Domain\Contracts\DecantPackPlanRepository;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\EloquentDecantPackPlanRepository;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\PostgresPublicPackPlanReader;
use Src\Decants\Packs\Infrastructure\Settings\BusinessSettingsDecantSizes;

final class PacksServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        DecantPackPlanRepository::class => EloquentDecantPackPlanRepository::class,
        PublicPackPlanReader::class => PostgresPublicPackPlanReader::class,
        DecantSizes::class => BusinessSettingsDecantSizes::class,
    ];
}
