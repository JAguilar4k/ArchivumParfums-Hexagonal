<?php

declare(strict_types=1);

use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackPlanId;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

/** The initial offer of the specification (R32): 5 decants of 5 ml for 180 days, here with a cap of ₡2 500 per ml. */
function domainPackPlan(?int $cap = 2500): DecantPackPlan
{
    return DecantPackPlan::create(new DecantPackPlanId('0192f0c4-0000-7000-9000-000000000101'),
        new BusinessId('0192f0c4-0000-7000-9000-000000000002'), new PlanName('  Descubrimiento  '),
        new DecantCount(5), new MlPerDecant(5), new PackPrice(40000), new ValidityDays(180),
        $cap === null ? null : new MaxPricePerMl($cap));
}

it('R32: defines an offer on sale with its decants, their ml, its price, its validity and an optional cap per ml', function () {
    $plan = domainPackPlan();

    expect($plan->name->value)->toBe('Descubrimiento')
        ->and($plan->decants->value)->toBe(5)
        ->and($plan->mlPerDecant->value)->toBe(5)
        ->and($plan->price->amount)->toBe(40000)
        ->and($plan->validity->days)->toBe(180)
        ->and($plan->maxPricePerMl?->amount)->toBe(2500)
        ->and($plan->isActive)->toBeTrue()
        ->and(domainPackPlan(null)->maxPricePerMl)->toBeNull();
});

it('R32: refuses decants, ml, price, validity and cap that are not positive integers within their limits', function (Closure $value, string $key) {
    expect($value)->toThrow(InvalidValueException::class, $key);
})->with([
    'no decants' => [fn () => new DecantCount(0), 'packs.decants_invalid'],
    'negative decants' => [fn () => new DecantCount(-5), 'packs.decants_invalid'],
    'more decants than the limit' => [fn () => new DecantCount(DecantCount::MAX + 1), 'packs.decants_invalid'],
    'no ml' => [fn () => new MlPerDecant(0), 'packs.ml_invalid'],
    'negative ml' => [fn () => new MlPerDecant(-5), 'packs.ml_invalid'],
    'more ml than the column holds' => [fn () => new MlPerDecant(MlPerDecant::MAX + 1), 'packs.ml_invalid'],
    'a free pack' => [fn () => new PackPrice(0), 'packs.price_invalid'],
    'a negative price' => [fn () => new PackPrice(-40000), 'packs.price_invalid'],
    'a price the column cannot hold' => [fn () => new PackPrice(Colones::MAX + 1), 'packs.price_invalid'],
    'no validity' => [fn () => new ValidityDays(0), 'packs.validity_invalid'],
    'a negative validity' => [fn () => new ValidityDays(-180), 'packs.validity_invalid'],
    'a validity longer than the limit' => [fn () => new ValidityDays(ValidityDays::MAX + 1), 'packs.validity_invalid'],
    'a cap of nothing' => [fn () => new MaxPricePerMl(0), 'packs.cap_invalid'],
    'a negative cap' => [fn () => new MaxPricePerMl(-2500), 'packs.cap_invalid'],
    'a cap the column cannot hold' => [fn () => new MaxPricePerMl(Colones::MAX + 1), 'packs.cap_invalid'],
    'an empty name' => [fn () => new PlanName('   '), 'packs.name_invalid'],
    'a name too long' => [fn () => new PlanName(str_repeat('a', PlanName::MAX_LENGTH + 1)), 'packs.name_invalid'],
]);

it('R32: accepts each value at its limits', function () {
    expect(new DecantCount(1)->value)->toBe(1)
        ->and(new DecantCount(DecantCount::MAX)->value)->toBe(50)
        ->and(new MlPerDecant(MlPerDecant::MAX)->value)->toBe(32767)
        ->and(new PackPrice(Colones::MAX)->amount)->toBe(Colones::MAX)
        ->and(new ValidityDays(1)->days)->toBe(1)
        ->and(new ValidityDays(ValidityDays::MAX)->days)->toBe(365)
        ->and(new MaxPricePerMl(1)->amount)->toBe(1)
        ->and(new PlanName(str_repeat('a', PlanName::MAX_LENGTH))->value)->toHaveLength(120);
});

it('R32: compares the cap with the sale price of the presentation, without decimals', function () {
    $cap = new MaxPricePerMl(2500);

    expect($cap->allows(250000, 100))->toBeTrue()
        ->and($cap->allows(250001, 100))->toBeFalse()
        ->and($cap->allows(98000, 50))->toBeTrue()
        ->and($cap->allows(135000, 50))->toBeFalse()
        ->and(new MaxPricePerMl(Colones::MAX)->allows(Colones::MAX, 32767))->toBeTrue();
});

it('R32: deactivating an offer keeps it, and it can go back on sale', function () {
    $plan = domainPackPlan();

    $plan->deactivate();

    expect($plan->isActive)->toBeFalse()
        ->and($plan->name->value)->toBe('Descubrimiento')
        ->and($plan->price->amount)->toBe(40000);

    $plan->reactivate();

    expect($plan->isActive)->toBeTrue();
});

it('rebuilds an offer from its stored state', function () {
    $plan = DecantPackPlan::reconstitute(new DecantPackPlanId('0192f0c4-0000-7000-9000-000000000102'),
        new BusinessId('0192f0c4-0000-7000-9000-000000000002'), new PlanName('Diez de 2 ml'),
        new DecantCount(10), new MlPerDecant(2), new PackPrice(25000), new ValidityDays(90), null, false);

    expect($plan->id->value)->toBe('0192f0c4-0000-7000-9000-000000000102')
        ->and($plan->businessId->value)->toBe('0192f0c4-0000-7000-9000-000000000002')
        ->and($plan->decants->value)->toBe(10)
        ->and($plan->mlPerDecant->value)->toBe(2)
        ->and($plan->validity->days)->toBe(90)
        ->and($plan->maxPricePerMl)->toBeNull()
        ->and($plan->isActive)->toBeFalse();
});
