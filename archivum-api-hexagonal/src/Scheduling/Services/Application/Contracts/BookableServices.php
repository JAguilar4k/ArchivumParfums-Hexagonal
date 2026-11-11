<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application\Contracts;

use Src\Scheduling\Services\Application\DTOs\BookableService;

/** Port of the services a visitor may book in the business of the address; only the fields of the public contract leave it. */
interface BookableServices
{
    /**
     * The active services of the business of the context, ordered by name.
     *
     * @return list<BookableService>
     */
    public function active(): array;
}
