<?php

declare(strict_types=1);

use Src\Businesses\Profile\Application\UseCases\GetBusinessProfile;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\BusinessNotFoundException;
use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Tests\Support\Businesses\InMemoryBusinessRepository;

it('reads the profile of a business', function () {
    $businesses = new InMemoryBusinessRepository;
    $business = Business::create(
        $businesses->nextId(),
        new BusinessName('ArchivumParfums'),
        new BusinessSlug('archivumparfums'),
        new BusinessTimezone('America/Costa_Rica'),
        null,
    );
    $businesses->save($business);

    expect(new GetBusinessProfile($businesses)->execute($business->id->value))->toBe($business);
});

it('reports a business that does not exist', function () {
    new GetBusinessProfile(new InMemoryBusinessRepository)->execute('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99');
})->throws(BusinessNotFoundException::class);
