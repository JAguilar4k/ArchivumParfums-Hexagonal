<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\Contracts;

use Src\Businesses\Members\Application\DTOs\MemberProfile;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

interface MemberDirectory
{
    /**
     * List the staff of a business with the name and the email of each account.
     *
     * @return list<MemberProfile>
     */
    public function all(BusinessId $businessId): array;

    /** Find one person of the staff of a business. */
    public function find(MemberId $id, BusinessId $businessId): ?MemberProfile;
}
