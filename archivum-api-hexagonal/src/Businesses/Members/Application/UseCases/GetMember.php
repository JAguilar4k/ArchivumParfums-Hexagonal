<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\UseCases;

use Src\Businesses\Members\Application\Contracts\MemberDirectory;
use Src\Businesses\Members\Application\DTOs\MemberProfile;
use Src\Businesses\Members\Domain\Exceptions\MemberNotFoundException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

final readonly class GetMember
{
    /** Receive the port that reads the staff. */
    public function __construct(private MemberDirectory $directory) {}

    /**
     * Show one person of the staff of a business; a person of another business does not exist here.
     *
     * @throws MemberNotFoundException
     */
    public function execute(string $businessId, string $memberId): MemberProfile
    {
        return $this->directory->find(new MemberId($memberId), new BusinessId($businessId))
            ?? throw MemberNotFoundException::create();
    }
}
