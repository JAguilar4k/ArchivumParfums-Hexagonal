<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Application\UseCases;

use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class FindBusiness
{
    /** Receive the business repository port. */
    public function __construct(private BusinessRepository $businesses) {}

    /** Find a business by its identity, or null when it does not exist. */
    public function execute(string $businessId): ?Business
    {
        return $this->businesses->find(new BusinessId($businessId));
    }
}
