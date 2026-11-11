<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application\UseCases;

use Src\Scheduling\Services\Application\Contracts\BookableServices;
use Src\Scheduling\Services\Application\DTOs\BookableService;

/** List the services a visitor may book in the business of the address. */
final readonly class ListBookableServices
{
    /** Receive the reader port. */
    public function __construct(private BookableServices $services) {}

    /**
     * The active services of the business of the context, ordered by name.
     *
     * @return list<BookableService>
     */
    public function execute(): array
    {
        return $this->services->active();
    }
}
