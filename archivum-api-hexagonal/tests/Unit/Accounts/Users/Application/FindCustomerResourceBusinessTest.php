<?php

declare(strict_types=1);

use Src\Accounts\Users\Application\UseCases\FindCustomerResourceBusiness;
use Src\Accounts\Users\Application\UseCases\ListCustomerBusinesses;
use Src\Accounts\Users\Domain\Exceptions\CustomerResourceNotFoundException;
use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceType;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Accounts\InMemoryCustomerBusinesses;

const RESOURCE_CUSTOMER = '0192f0c4-0000-7000-8000-000000000011';
const RESOURCE_OTHER_CUSTOMER = '0192f0c4-0000-7000-8000-000000000012';
const RESOURCE_OF_THE_CUSTOMER = '0192f0c4-0000-7000-a000-000000000011';
const RESOURCE_BUSINESS = '0192f0c4-0000-7000-9000-000000000011';

/** Wire the port with one resource of RESOURCE_CUSTOMER in RESOURCE_BUSINESS. */
function customerResourcesWiring(): InMemoryCustomerBusinesses
{
    $businesses = new InMemoryCustomerBusinesses;
    $businesses->add(CustomerResourceType::Customer, RESOURCE_OF_THE_CUSTOMER, RESOURCE_CUSTOMER, new BusinessId(RESOURCE_BUSINESS));

    return $businesses;
}

it('finds the business of a resource of the customer', function () {
    $business = (new FindCustomerResourceBusiness(customerResourcesWiring()))
        ->execute(CustomerResourceType::Customer, RESOURCE_OF_THE_CUSTOMER, RESOURCE_CUSTOMER);

    expect($business->value)->toBe(RESOURCE_BUSINESS);
});

it('answers a resource of another customer, a missing one and a malformed identifier as not found', function (string $resourceId, string $userId) {
    (new FindCustomerResourceBusiness(customerResourcesWiring()))->execute(CustomerResourceType::Customer, $resourceId, $userId);
})->throws(CustomerResourceNotFoundException::class)->with([
    'another customer' => [RESOURCE_OF_THE_CUSTOMER, RESOURCE_OTHER_CUSTOMER],
    'missing' => [RESOURCE_OTHER_CUSTOMER, RESOURCE_CUSTOMER],
    'malformed' => ['no-es-un-identificador', RESOURCE_CUSTOMER],
]);

it('lists the businesses where the account is a customer', function () {
    $listBusinesses = new ListCustomerBusinesses(customerResourcesWiring());

    expect($listBusinesses->execute(RESOURCE_CUSTOMER))->toEqual([new BusinessId(RESOURCE_BUSINESS)])
        ->and($listBusinesses->execute(RESOURCE_OTHER_CUSTOMER))->toBe([]);
});
