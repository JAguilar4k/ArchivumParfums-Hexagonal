<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Decants\Bottles\Application\Contracts\BottleReader;
use Src\Decants\Bottles\Domain\Contracts\DecantBottleRepository;
use Src\Decants\Bottles\Domain\Contracts\MlReservation;
use Src\Decants\Bottles\Infrastructure\Persistence\Repositories\EloquentDecantBottleRepository;
use Src\Decants\Bottles\Infrastructure\Persistence\Repositories\PostgresBottleReader;
use Src\Decants\Bottles\Infrastructure\Persistence\Repositories\PostgresMlReservation;

final class BottlesServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        DecantBottleRepository::class => EloquentDecantBottleRepository::class,
        MlReservation::class => PostgresMlReservation::class,
        BottleReader::class => PostgresBottleReader::class,
    ];
}
