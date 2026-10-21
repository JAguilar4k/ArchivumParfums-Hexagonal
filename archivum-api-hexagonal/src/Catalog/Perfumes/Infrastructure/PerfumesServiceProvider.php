<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Repositories\EloquentPerfumeRepository;

final class PerfumesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PerfumeRepository::class => EloquentPerfumeRepository::class,
    ];
}
