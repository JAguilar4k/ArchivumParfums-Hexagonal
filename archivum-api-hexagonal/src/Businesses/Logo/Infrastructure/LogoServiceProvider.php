<?php

declare(strict_types=1);

namespace Src\Businesses\Logo\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Businesses\Logo\Application\Contracts\BusinessLogos;
use Src\Businesses\Logo\Infrastructure\Persistence\EloquentBusinessLogos;
use Src\Shared\Domain\Contracts\FileStorage;
use Src\Shared\Infrastructure\Storage\LaravelFileStorage;

final class LogoServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        BusinessLogos::class => EloquentBusinessLogos::class,
        FileStorage::class => LaravelFileStorage::class,
    ];
}
