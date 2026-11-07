<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\UseCases;

use Src\Businesses\Members\Application\Contracts\MemberDirectory;
use Src\Businesses\Members\Application\DTOs\MemberProfile;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class ListMembers
{
    /** Receive the port that reads the staff. */
    public function __construct(private MemberDirectory $directory) {}

    /**
     * List the staff of a business, deactivated people included.
     *
     * @return list<MemberProfile>
     */
    public function execute(string $businessId): array
    {
        return $this->directory->all(new BusinessId($businessId));
    }
}
