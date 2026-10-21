<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Catalog\Showcase\Application\Contracts\ShowcaseReader;
use Src\Catalog\Showcase\Infrastructure\Persistence\PostgresShowcaseReader;
use Src\Shared\Domain\Contracts\FileStorage;
use Src\Shared\Infrastructure\Storage\LaravelFileStorage;

final class ShowcaseServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ShowcaseReader::class => PostgresShowcaseReader::class,
        FileStorage::class => LaravelFileStorage::class,
    ];
}
