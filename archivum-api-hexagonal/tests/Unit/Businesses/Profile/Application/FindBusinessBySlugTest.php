<?php

declare(strict_types=1);

use Src\Businesses\Profile\Application\UseCases\FindBusinessBySlug;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\BusinessNotFoundException;
use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Tests\Support\Businesses\InMemoryBusinessRepository;

/**
 * Wire the use case to an in-memory repository with the business archivumparfums.
 *
 * @return array{FindBusinessBySlug, Business}
 */
function findBusinessBySlugWiring(): array
{
    $businesses = new InMemoryBusinessRepository;
    $business = Business::create(
        $businesses->nextId(),
        new BusinessName('ArchivumParfums'),
        new BusinessSlug('archivumparfums'),
        new BusinessTimezone('America/Costa_Rica'),
        null,
    );
    $businesses->save($business);

    return [new FindBusinessBySlug($businesses), $business];
}

it('finds the business of a slug', function () {
    [$findBusiness, $business] = findBusinessBySlugWiring();

    expect($findBusiness->execute('archivumparfums'))->toBe($business);
});

it('answers an unknown and a malformed slug as a business that does not exist', function (string $slug) {
    [$findBusiness] = findBusinessBySlugWiring();

    $findBusiness->execute($slug);
})->throws(BusinessNotFoundException::class)->with(['unknown' => 'no-existe', 'malformed' => 'Archivum Parfums']);
