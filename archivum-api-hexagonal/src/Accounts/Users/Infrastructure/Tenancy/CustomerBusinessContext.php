<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Tenancy;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use LogicException;
use Src\Accounts\Users\Application\UseCases\FindCustomerResourceBusiness;
use Src\Accounts\Users\Application\UseCases\ListCustomerBusinesses;
use Src\Accounts\Users\Domain\Exceptions\CustomerResourceNotFoundException;
use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceType;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final class CustomerBusinessContext implements BusinessContext
{
    /**
     * Route parameters that name a resource of the customer, with the type fn_customer_resource_business knows it by.
     * Each issue that adds a resource of the customer adds its parameter: {cita} with #59, {pedido} with #78.
     */
    public const array RESOURCES = ['cliente' => CustomerResourceType::Customer];

    private ?BusinessId $business = null;

    /** Receive the authentication, the request and the use cases that find the businesses of a customer. */
    public function __construct(
        private readonly Auth $auth,
        private readonly Request $request,
        private readonly FindCustomerResourceBusiness $findBusiness,
        private readonly ListCustomerBusinesses $listBusinesses,
        private readonly BusinessTransaction $transaction,
    ) {}

    /**
     * Answer the business of the resource of the customer named in the route, or the business of the pass of
     * eachBusiness() that is running; a resource of another customer is not found.
     *
     * @throws AuthenticationException
     * @throws CustomerResourceNotFoundException
     */
    public function current(): BusinessId
    {
        if ($this->business !== null) {
            return $this->business;
        }

        [$type, $resourceId] = $this->resourceOfTheRoute();

        return $this->business = $this->findBusiness->execute($type, $resourceId, $this->userId());
    }

    /**
     * Run the operation once per business where the account is a customer, each time in its own transaction pinned
     * to that business, and collect what each pass returns: the listings of /api/v1/cuenta read one business at a
     * time. It runs outside the business middleware, so no outer transaction keeps the business of a pass.
     *
     * @template TResult
     *
     * @param  Closure(BusinessId): TResult  $operation
     * @return list<TResult>
     *
     * @throws AuthenticationException
     */
    public function eachBusiness(Closure $operation): array
    {
        $previous = $this->business;
        $results = [];

        try {
            foreach ($this->listBusinesses->execute($this->userId()) as $businessId) {
                $this->business = $businessId;
                $results[] = $this->transaction->run($businessId->value, fn (): mixed => $operation($businessId));
            }
        } finally {
            $this->business = $previous;
        }

        return $results;
    }

    /**
     * Identifier of the authenticated account.
     *
     * @throws AuthenticationException
     */
    private function userId(): string
    {
        $userId = $this->auth->guard()->id() ?? throw new AuthenticationException;

        return (string) $userId;
    }

    /**
     * Type and identifier of the resource of the customer named by the route.
     *
     * @return array{CustomerResourceType, string}
     */
    private function resourceOfTheRoute(): array
    {
        foreach (self::RESOURCES as $parameter => $type) {
            $resourceId = $this->request->route($parameter);

            if ($resourceId !== null) {
                return [$type, is_string($resourceId) ? $resourceId : ''];
            }
        }

        throw new LogicException('A route with business:account names no resource of the customer.');
    }
}
