<?php

declare(strict_types=1);

use Src\Catalog\Perfumes\Application\DTOs\CreatePerfumeCommand;
use Src\Catalog\Perfumes\Application\UseCases\CreatePerfume;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicatePerfumeException;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Catalog\InMemoryPerfumeRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

const CREATE_PERFUME_BUSINESS = '0192f0c4-aaaa-7000-9000-000000000001';
const CREATE_PERFUME_OTHER_BUSINESS = '0192f0c4-aaaa-7000-9000-000000000002';

/**
 * Wire the use case to an in-memory repository and a session of the first business.
 *
 * @return array{CreatePerfume, InMemoryPerfumeRepository, FixedBusinessContext, RecordingTransactionRunner}
 */
function createPerfumeWiring(): array
{
    $perfumes = new InMemoryPerfumeRepository;
    $context = new FixedBusinessContext(new BusinessId(CREATE_PERFUME_BUSINESS));
    $transaction = new RecordingTransactionRunner;

    return [new CreatePerfume($perfumes, $context, $transaction), $perfumes, $context, $transaction];
}

/** A fictitious command, with the parts that a test changes given by name. */
function createPerfumeCommand(
    string $brand = 'Tom Ford',
    string $name = 'Oud Wood',
    ?string $concentration = 'EDP',
    bool $isActive = true,
): CreatePerfumeCommand {
    return new CreatePerfumeCommand(
        $brand,
        $name,
        $concentration,
        'unisex',
        'Amaderada',
        null,
        ['top' => ['Cardamomo'], 'heart' => [], 'base' => ['Oud', 'Sándalo']],
        ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => ['Cena']],
        $isActive,
    );
}

it('creates the perfume with its notes and tags in the business of the session', function () {
    [$createPerfume, $perfumes, , $transaction] = createPerfumeWiring();

    $perfume = $createPerfume->execute(createPerfumeCommand());

    expect($perfume->businessId->value)->toBe(CREATE_PERFUME_BUSINESS)
        ->and($perfume->brand->value)->toBe('Tom Ford')
        ->and($perfume->isActive)->toBeTrue()
        ->and($perfume->notes)->toHaveCount(3)
        ->and($perfume->tags)->toHaveCount(3)
        ->and($perfume->variants)->toBe([])
        ->and($perfumes->find($perfume->id))->toBe($perfume)
        ->and($transaction->runs())->toBe(1);
});

it('creates the perfume inactive when the owner asks for it', function () {
    [$createPerfume] = createPerfumeWiring();

    expect($createPerfume->execute(createPerfumeCommand(isActive: false))->isActive)->toBeFalse();
});

it('takes the business from the session, which can be any of them', function () {
    [$createPerfume, , $context] = createPerfumeWiring();
    $context->switchTo(new BusinessId(CREATE_PERFUME_OTHER_BUSINESS));

    expect($createPerfume->execute(createPerfumeCommand())->businessId->value)->toBe(CREATE_PERFUME_OTHER_BUSINESS);
});

it('refuses a repeated perfume in the same business but not in another one', function () {
    [$createPerfume, $perfumes, $context] = createPerfumeWiring();
    $createPerfume->execute(createPerfumeCommand());

    expect(fn () => $createPerfume->execute(createPerfumeCommand(brand: 'TOM FORD', name: 'oud wood')))
        ->toThrow(DuplicatePerfumeException::class, 'catalog.perfume_already_exists');

    // The same name in another concentration is another perfume.
    $createPerfume->execute(createPerfumeCommand(concentration: 'EDT'));

    $context->switchTo(new BusinessId(CREATE_PERFUME_OTHER_BUSINESS));
    $createPerfume->execute(createPerfumeCommand());

    expect($perfumes->count())->toBe(3);
});

it('rejects the data the domain cannot hold and stores nothing', function (Closure $command, string $key) {
    [$createPerfume, $perfumes] = createPerfumeWiring();

    expect(fn () => $createPerfume->execute($command()))->toThrow(InvalidValueException::class, $key)
        ->and($perfumes->count())->toBe(0);
})->with([
    'empty brand' => [fn () => createPerfumeCommand(brand: ' '), 'catalog.brand_invalid'],
    'empty name' => [fn () => createPerfumeCommand(name: ''), 'catalog.name_invalid'],
    'moment outside the list' => [
        fn () => new CreatePerfumeCommand('A', 'B', null, null, null, null, ['top' => [], 'heart' => [], 'base' => []], ['time_of_day' => ['evening'], 'climate' => [], 'occasion' => []], true),
        'catalog.time_of_day_invalid',
    ],
    'climate outside the list' => [
        fn () => new CreatePerfumeCommand('A', 'B', null, null, null, null, ['top' => [], 'heart' => [], 'base' => []], ['time_of_day' => [], 'climate' => ['hot'], 'occasion' => []], true),
        'catalog.climate_invalid',
    ],
]);
