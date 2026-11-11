<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\Contracts;

interface PackCustomers
{
    /** The customer record of the account in the business of the session, created with its first purchase there. */
    public function findOrCreate(string $userId): string;
}
