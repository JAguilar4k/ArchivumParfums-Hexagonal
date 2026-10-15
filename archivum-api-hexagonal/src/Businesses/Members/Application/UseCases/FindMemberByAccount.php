<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\UseCases;

use Src\Businesses\Members\Domain\Contracts\MemberRepository;
use Src\Businesses\Members\Domain\ValueObjects\Membership;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class FindMemberByAccount
{
    /** Receive the member repository port. */
    public function __construct(private MemberRepository $members) {}

    /** Find the business and role of an account, or null when the account is on no staff. */
    public function execute(string $userId): ?Membership
    {
        return $this->members->findByUser(new UserId($userId));
    }
}
