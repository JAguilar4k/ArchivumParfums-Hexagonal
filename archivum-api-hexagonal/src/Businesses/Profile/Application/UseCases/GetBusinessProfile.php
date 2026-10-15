<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Application\UseCases;

use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\BusinessNotFoundException;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class GetBusinessProfile
{
    /** Receive the business repository port. */
    public function __construct(private BusinessRepository $businesses) {}

    /** Read the profile and the settings of a business. */
    public function execute(string $businessId): Business
    {
        return $this->businesses->find(new BusinessId($businessId))
            ?? throw BusinessNotFoundException::create();
    }
}
