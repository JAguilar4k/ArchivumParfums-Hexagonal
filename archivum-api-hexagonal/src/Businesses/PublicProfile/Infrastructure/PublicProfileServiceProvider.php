<?php

declare(strict_types=1);

namespace Src\Businesses\PublicProfile\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Businesses\PublicProfile\Application\Contracts\PublicBusinessReader;
use Src\Businesses\PublicProfile\Infrastructure\Persistence\EloquentPublicBusinessReader;
use Src\Shared\Domain\Contracts\FileStorage;
use Src\Shared\Infrastructure\Storage\LaravelFileStorage;

final class PublicProfileServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PublicBusinessReader::class => EloquentPublicBusinessReader::class,
        FileStorage::class => LaravelFileStorage::class,
    ];
}
