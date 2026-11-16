<?php

declare(strict_types=1);

use Src\Decants\Packs\Application\DTOs\CreateDecantPackPlanCommand;
use Src\Decants\Packs\Application\UseCases\CreateDecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantSizeNotSoldException;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\FixedDecantSizes;
use Tests\Support\Decants\InMemoryDecantPackPlanRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

const CREATE_PACK_PLAN_BUSINESS = '0192f0c4-cccc-7000-9000-000000000001';
const CREATE_PACK_PLAN_OTHER_BUSINESS = '0192f0c4-cccc-7000-9000-000000000002';

/**
 * Wire the use case to an in-memory repository, the default decant sizes and a session of the first business.
 *
 * @param  list<int>  $sizes
 * @return array{CreateDecantPackPlan, InMemoryDecantPackPlanRepository, FixedBusinessContext, RecordingTransactionRunner}
 */
function createPackPlanWiring(array $sizes = [2, 5, 10]): array
{
    $context = new FixedBusinessContext(new BusinessId(CREATE_PACK_PLAN_BUSINESS));
    $plans = new InMemoryDecantPackPlanRepository($context);
    $transaction = new RecordingTransactionRunner;

    return [new CreateDecantPackPlan($plans, new FixedDecantSizes($sizes), $context, $transaction), $plans, $context, $transaction];
}

/** The initial offer of the specification, with the parts that a test changes given by name. */
function createPackPlanCommand(int $decants = 5, int $ml = 5, int $price = 40000, int $days = 180, ?int $cap = 2500, bool $isActive = true): CreateDecantPackPlanCommand
{
    return new CreateDecantPackPlanCommand('Descubrimiento', $decants, $ml, $price, $days, $cap, $isActive);
}

it('R32: defines the offer on sale in the business of the session', function () {
    [$createPlan, $plans, , $transaction] = createPackPlanWiring();

    $plan = $createPlan->execute(createPackPlanCommand());

    expect($plan->businessId->value)->toBe(CREATE_PACK_PLAN_BUSINESS)
        ->and($plan->name->value)->toBe('Descubrimiento')
        ->and($plan->decants->value)->toBe(5)
        ->and($plan->mlPerDecant->value)->toBe(5)
        ->and($plan->price->amount)->toBe(40000)
        ->and($plan->validity->days)->toBe(180)
        ->and($plan->maxPricePerMl?->amount)->toBe(2500)
        ->and($plan->isActive)->toBeTrue()
        ->and($plans->find($plan->id))->toBe($plan)
        ->and($transaction->committed())->toBe(1);
});

it('R32: defines an offer without a cap, or off sale when the owner asks for it', function () {
    [$createPlan] = createPackPlanWiring();

    expect($createPlan->execute(createPackPlanCommand(cap: null))->maxPricePerMl)->toBeNull()
        ->and($createPlan->execute(createPackPlanCommand(isActive: false))->isActive)->toBeFalse();
});

it('takes the business from the session, which can be any of them', function () {
    [$createPlan, , $context] = createPackPlanWiring();
    $context->switchTo(new BusinessId(CREATE_PACK_PLAN_OTHER_BUSINESS));

    expect($createPlan->execute(createPackPlanCommand())->businessId->value)->toBe(CREATE_PACK_PLAN_OTHER_BUSINESS);
});

it('R4: accepts only ml that are one of the decant sizes of the store, and stores nothing otherwise', function () {
    [$createPlan, $plans, , $transaction] = createPackPlanWiring([2, 5, 10]);

    expect(fn () => $createPlan->execute(createPackPlanCommand(ml: 7)))->toThrow(DecantSizeNotSoldException::class, 'packs.size_not_sold')
        ->and($plans->count())->toBe(0)
        ->and($transaction->rolledBack())->toBe(1);

    [$createPlan] = createPackPlanWiring([3, 7]);

    expect($createPlan->execute(createPackPlanCommand(ml: 7))->mlPerDecant->value)->toBe(7);
});

it('R32: rejects the values the domain cannot hold and stores nothing', function (CreateDecantPackPlanCommand $command, string $key) {
    [$createPlan, $plans] = createPackPlanWiring();

    expect(fn () => $createPlan->execute($command))->toThrow(InvalidValueException::class, $key)
        ->and($plans->count())->toBe(0);
})->with([
    'no decants' => [fn () => createPackPlanCommand(decants: 0), 'packs.decants_invalid'],
    'more than 50 decants' => [fn () => createPackPlanCommand(decants: 51), 'packs.decants_invalid'],
    'a free pack' => [fn () => createPackPlanCommand(price: 0), 'packs.price_invalid'],
    'more than 365 days' => [fn () => createPackPlanCommand(days: 366), 'packs.validity_invalid'],
    'a cap of nothing' => [fn () => createPackPlanCommand(cap: 0), 'packs.cap_invalid'],
]);
