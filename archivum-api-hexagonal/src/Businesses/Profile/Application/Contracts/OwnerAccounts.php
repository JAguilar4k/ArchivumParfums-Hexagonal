<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Application\Contracts;

use SensitiveParameter;
use Src\Shared\Domain\ValueObjects\BusinessId;

interface OwnerAccounts
{
    /** Open the account of the owner of a business and add it to its staff as owner. */
    public function registerOwner(
        BusinessId $businessId,
        string $name,
        string $email,
        #[SensitiveParameter] string $password,
        bool $attendsAppointments,
    ): void;
}
