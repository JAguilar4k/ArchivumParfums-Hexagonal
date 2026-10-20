<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\UseCases;

use Src\Accounts\Users\Application\Contracts\CustomerBusinesses;
use Src\Accounts\Users\Domain\Exceptions\CustomerResourceNotFoundException;
use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceId;
use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceType;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class FindCustomerResourceBusiness
{
    /** Receive the port that answers the businesses of a customer. */
    public function __construct(private CustomerBusinesses $businesses) {}

    /**
     * Find the business of a resource of the customer; a malformed identifier, a resource of another customer and
     * one that does not exist are the same resource that is not found.
     *
     * @throws CustomerResourceNotFoundException
     */
    public function execute(CustomerResourceType $type, string $resourceId, string $userId): BusinessId
    {
        try {
            $id = new CustomerResourceId($resourceId);
        } catch (InvalidValueException) {
            throw CustomerResourceNotFoundException::create();
        }

        return $this->businesses->ofResource($type, $id, new UserId($userId))
            ?? throw CustomerResourceNotFoundException::create();
    }
}
