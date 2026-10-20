<?php

declare(strict_types=1);

use Src\Accounts\Users\Application\UseCases\ListCustomerBusinesses;
use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceType;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Accounts\InMemoryCustomerBusinesses;

const LISTED_CUSTOMER = '0192f0c4-0000-7000-8000-000000000031';
const LISTED_OTHER_CUSTOMER = '0192f0c4-0000-7000-8000-000000000032';
const LISTED_FIRST_BUSINESS = '0192f0c4-0000-7000-9000-000000000031';
const LISTED_SECOND_BUSINESS = '0192f0c4-0000-7000-9000-000000000032';

it('lists the businesses where the account is a customer and none of another account', function () {
    $businesses = new InMemoryCustomerBusinesses;
    $businesses->add(CustomerResourceType::Customer, '0192f0c4-0000-7000-a000-000000000031', LISTED_CUSTOMER, new BusinessId(LISTED_FIRST_BUSINESS));
    $businesses->add(CustomerResourceType::Customer, '0192f0c4-0000-7000-a000-000000000032', LISTED_CUSTOMER, new BusinessId(LISTED_SECOND_BUSINESS));
    $listBusinesses = new ListCustomerBusinesses($businesses);

    expect($listBusinesses->execute(LISTED_CUSTOMER))->toEqual([new BusinessId(LISTED_FIRST_BUSINESS), new BusinessId(LISTED_SECOND_BUSINESS)])
        ->and($listBusinesses->execute(LISTED_OTHER_CUSTOMER))->toBe([]);
});
