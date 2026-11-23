<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Application\UseCases;

use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\Entities\Business;

final readonly class ListBusinesses
{
    /** Receive the business repository port. */
    public function __construct(private BusinessRepository $businesses) {}

    /**
     * List every business of the platform by name, for its administrator (#347).
     *
     * @return list<Business>
     */
    public function execute(): array
    {
        return $this->businesses->allByName();
    }
}
