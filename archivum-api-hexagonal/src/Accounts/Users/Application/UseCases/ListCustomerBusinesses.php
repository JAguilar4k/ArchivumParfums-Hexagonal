<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\UseCases;

use Src\Accounts\Users\Domain\Contracts\CustomerBusinesses;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class ListCustomerBusinesses
{
    /** Receive the port that answers the businesses of a customer. */
    public function __construct(private CustomerBusinesses $businesses) {}

    /**
     * List the businesses where the account is a customer.
     *
     * @return list<BusinessId>
     */
    public function execute(string $userId): array
    {
        return $this->businesses->of(new UserId($userId));
    }
}
