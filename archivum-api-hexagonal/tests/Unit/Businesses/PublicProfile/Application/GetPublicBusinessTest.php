<?php

declare(strict_types=1);

use Src\Businesses\PublicProfile\Application\Contracts\PublicBusinessReader;
use Src\Businesses\PublicProfile\Application\DTOs\PublicBusinessRecord;
use Src\Businesses\PublicProfile\Application\Exceptions\PublicBusinessNotFoundException;
use Src\Businesses\PublicProfile\Application\UseCases\GetPublicBusiness;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Shared\InMemoryFileStorage;

const PUBLIC_BUSINESS_ID = '0192f0c4-aaaa-7000-9000-0000000000a1';
const OTHER_PUBLIC_BUSINESS_ID = '0192f0c4-aaaa-7000-9000-0000000000a2';

/**
 * Wire the use case to a reader that knows two businesses and a session on the first one.
 *
 * @return array{GetPublicBusiness, FixedBusinessContext}
 */
function publicBusinessWiring(): array
{
    $reader = new class implements PublicBusinessReader
    {
        public function find(BusinessId $business): ?PublicBusinessRecord
        {
            return match ($business->value) {
                PUBLIC_BUSINESS_ID => new PublicBusinessRecord('ArchivumParfums', 'archivumparfums', 'logos/archivum.png', 'Escazú, San Rafael', 'America/Costa_Rica'),
                OTHER_PUBLIC_BUSINESS_ID => new PublicBusinessRecord('Esencias del Norte', 'esencias-del-norte', null, null, 'America/Costa_Rica'),
                default => null,
            };
        }
    };
    $context = new FixedBusinessContext(new BusinessId(PUBLIC_BUSINESS_ID));

    return [new GetPublicBusiness($reader, new InMemoryFileStorage, $context), $context];
}

it('shows the public data of the business with the address of its logo', function () {
    [$getBusiness] = publicBusinessWiring();

    $view = $getBusiness->execute();

    expect($view->name)->toBe('ArchivumParfums')
        ->and($view->slug)->toBe('archivumparfums')
        ->and($view->logoUrl)->toBe('https://archivos.test/logos/archivum.png')
        ->and($view->appointmentPlace)->toBe('Escazú, San Rafael')
        ->and($view->timezone)->toBe('America/Costa_Rica');
});

it('leaves the logo and the place empty when the business has none', function () {
    [$getBusiness, $context] = publicBusinessWiring();
    $context->switchTo(new BusinessId(OTHER_PUBLIC_BUSINESS_ID));

    $view = $getBusiness->execute();

    expect($view->logoUrl)->toBeNull()
        ->and($view->appointmentPlace)->toBeNull();
});

it('treats a business that does not exist as not found', function () {
    [$getBusiness, $context] = publicBusinessWiring();
    $context->switchTo(new BusinessId('0192f0c4-aaaa-7000-9000-0000000000ff'));

    $getBusiness->execute();
})->throws(PublicBusinessNotFoundException::class, 'businesses.business_not_found');
