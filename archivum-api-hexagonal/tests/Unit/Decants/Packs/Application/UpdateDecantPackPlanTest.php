<?php

declare(strict_types=1);

use Src\Decants\Packs\Application\DTOs\UpdateDecantPackPlanCommand;
use Src\Decants\Packs\Application\UseCases\UpdateDecantPackPlan;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantPackPlanNotFoundException;
use Src\Decants\Packs\Domain\Exceptions\DecantSizeNotSoldException;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\FixedDecantSizes;
use Tests\Support\Decants\InMemoryDecantPackPlanRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

const UPDATE_PACK_PLAN_BUSINESS = '0192f0c4-dddd-7000-9000-000000000001';
const UPDATE_PACK_PLAN_OTHER_BUSINESS = '0192f0c4-dddd-7000-9000-000000000002';

/**
 * Wire the use case to an in-memory repository that already holds the initial offer of the first business.
 *
 * @param  list<int>  $sizes
 * @return array{UpdateDecantPackPlan, DecantPackPlan, FixedBusinessContext, InMemoryDecantPackPlanRepository, RecordingTransactionRunner}
 */
function updatePackPlanWiring(array $sizes = [2, 5, 10]): array
{
    $context = new FixedBusinessContext(new BusinessId(UPDATE_PACK_PLAN_BUSINESS));
    $plans = new InMemoryDecantPackPlanRepository($context);
    $plan = DecantPackPlan::create($plans->nextId(), $context->current(), new PlanName('Descubrimiento'), new DecantCount(5),
        new MlPerDecant(5), new PackPrice(40000), new ValidityDays(180), new MaxPricePerMl(2500));
    $plans->save($plan);
    $transaction = new RecordingTransactionRunner;

    return [new UpdateDecantPackPlan($plans, new FixedDecantSizes($sizes), $transaction), $plan, $context, $plans, $transaction];
}

it('R32: changes only the fields sent', function () {
    [$updatePlan, $plan] = updatePackPlanWiring();

    $updated = $updatePlan->execute(new UpdateDecantPackPlanCommand($plan->id->value, ['price' => 45000, 'validity_days' => 120]));

    expect($updated->price->amount)->toBe(45000)
        ->and($updated->validity->days)->toBe(120)
        ->and($updated->name->value)->toBe('Descubrimiento')
        ->and($updated->decants->value)->toBe(5)
        ->and($updated->mlPerDecant->value)->toBe(5)
        ->and($updated->maxPricePerMl?->amount)->toBe(2500)
        ->and($updated->isActive)->toBeTrue();
});

it('R32: reads the offer to be changed inside one transaction, so two edits at once do not overwrite each other', function () {
    [$updatePlan, $plan, , $plans, $transaction] = updatePackPlanWiring();

    $updatePlan->execute(new UpdateDecantPackPlanCommand($plan->id->value, ['price' => 45000]));

    expect($plans->lockedIds)->toBe([$plan->id->value])
        ->and($transaction->runs())->toBe(1)
        ->and($transaction->committed())->toBe(1);
});

it('R32: changes every field of the offer at once', function () {
    [$updatePlan, $plan] = updatePackPlanWiring();

    $updated = $updatePlan->execute(new UpdateDecantPackPlanCommand($plan->id->value, [
        'name' => 'Diez de 2 ml', 'decants' => 10, 'ml_per_decant' => 2, 'price' => 25000, 'validity_days' => 90, 'max_price_per_ml' => 3000,
    ]));

    expect([$updated->name->value, $updated->decants->value, $updated->mlPerDecant->value, $updated->price->amount, $updated->validity->days, $updated->maxPricePerMl?->amount])
        ->toBe(['Diez de 2 ml', 10, 2, 25000, 90, 3000]);
});

it('R32: removes the cap when it is sent empty and keeps it when it is not sent', function () {
    [$updatePlan, $plan] = updatePackPlanWiring();

    expect($updatePlan->execute(new UpdateDecantPackPlanCommand($plan->id->value, ['name' => 'Otro nombre']))->maxPricePerMl?->amount)->toBe(2500)
        ->and($updatePlan->execute(new UpdateDecantPackPlanCommand($plan->id->value, ['max_price_per_ml' => null]))->maxPricePerMl)->toBeNull();
});

it('R32: takes the offer off sale and puts it back without deleting it', function () {
    [$updatePlan, $plan] = updatePackPlanWiring();

    expect($updatePlan->execute(new UpdateDecantPackPlanCommand($plan->id->value, ['active' => false]))->isActive)->toBeFalse()
        ->and($updatePlan->execute(new UpdateDecantPackPlanCommand($plan->id->value, ['active' => true]))->isActive)->toBeTrue();
});

it('R4: accepts new ml only from the decant sizes of the store', function () {
    [$updatePlan, $plan] = updatePackPlanWiring([2, 5, 10]);

    expect(fn () => $updatePlan->execute(new UpdateDecantPackPlanCommand($plan->id->value, ['ml_per_decant' => 7, 'price' => 1])))
        ->toThrow(DecantSizeNotSoldException::class, 'packs.size_not_sold')
        ->and($plan->mlPerDecant->value)->toBe(5)
        ->and($plan->price->amount)->toBe(40000)
        ->and($updatePlan->execute(new UpdateDecantPackPlanCommand($plan->id->value, ['ml_per_decant' => 10]))->mlPerDecant->value)->toBe(10);
});

it('R4: keeps the ml of the offer when the store stopped selling that size, so another field can still change', function () {
    [$updatePlan, $plan] = updatePackPlanWiring([2, 10]);

    $updated = $updatePlan->execute(new UpdateDecantPackPlanCommand($plan->id->value, ['ml_per_decant' => 5, 'price' => 42000]));

    expect($updated->mlPerDecant->value)->toBe(5)
        ->and($updated->price->amount)->toBe(42000);
});

it('treats an offer of another business, an unknown one or an invalid identifier as missing', function (Closure $planId) {
    [$updatePlan, $plan, $context] = updatePackPlanWiring();
    $context->switchTo(new BusinessId(UPDATE_PACK_PLAN_OTHER_BUSINESS));

    expect(fn () => $updatePlan->execute(new UpdateDecantPackPlanCommand($planId($plan), ['active' => false])))
        ->toThrow(DecantPackPlanNotFoundException::class, 'packs.plan_not_found');

    $context->switchTo(new BusinessId(UPDATE_PACK_PLAN_BUSINESS));
    expect($plan->isActive)->toBeTrue();
})->with([
    'of another business' => [fn (DecantPackPlan $plan): string => $plan->id->value],
    'unknown' => [fn (DecantPackPlan $plan): string => '0192f0c4-0000-7000-9000-999999999999'],
    'not a valid identifier' => [fn (DecantPackPlan $plan): string => 'paquete'],
]);

it('rejects the values the domain cannot hold and leaves the offer as it was', function (array $changes, string $key) {
    [$updatePlan, $plan] = updatePackPlanWiring();

    expect(fn () => $updatePlan->execute(new UpdateDecantPackPlanCommand($plan->id->value, $changes)))->toThrow(InvalidValueException::class, $key)
        ->and($plan->decants->value)->toBe(5)
        ->and($plan->validity->days)->toBe(180);
})->with([
    'no decants' => [['decants' => 0], 'packs.decants_invalid'],
    'more than 365 days' => [['validity_days' => 366], 'packs.validity_invalid'],
    'an empty name' => [['name' => '  '], 'packs.name_invalid'],
]);
