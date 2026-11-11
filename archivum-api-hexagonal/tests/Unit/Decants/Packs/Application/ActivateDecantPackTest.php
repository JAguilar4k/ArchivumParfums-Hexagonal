<?php

declare(strict_types=1);

use Src\Decants\Packs\Application\DTOs\ActivateDecantPackCommand;
use Src\Decants\Packs\Application\UseCases\ActivateDecantPack;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantPackConflictException;
use Src\Decants\Packs\Domain\Exceptions\DecantPackNotFoundException;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\InMemoryDecantPackPlanRepository;
use Tests\Support\Decants\InMemoryDecantPackRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

const ACTIVATE_PACK_BUSINESS = '0192f0c4-ffff-7000-9000-000000000001';

/**
 * Wire the use case to in-memory adapters that hold a pack of a 180-day offer, bought at 15:00 of 10/11/2026 and
 * waiting for its payment until 15:30.
 *
 * @return array{ActivateDecantPack, DecantPack, FixedBusinessContext, InMemoryDecantPackRepository, RecordingTransactionRunner}
 */
function activationWiring(): array
{
    $context = new FixedBusinessContext(new BusinessId(ACTIVATE_PACK_BUSINESS));
    $plans = new InMemoryDecantPackPlanRepository($context);
    $plan = DecantPackPlan::create($plans->nextId(), $context->current(), new PlanName('Descubrimiento'), new DecantCount(5),
        new MlPerDecant(5), new PackPrice(40000), new ValidityDays(180), new MaxPricePerMl(2500));
    $plans->save($plan);
    $packs = new InMemoryDecantPackRepository($context);
    $pack = DecantPack::create($packs->nextId(), '0192f0c4-ffff-7000-9000-000000000301', $plan, new DateTimeImmutable('2026-11-10T15:30:00-06:00'));
    $packs->add($pack);
    $transaction = new RecordingTransactionRunner;

    return [new ActivateDecantPack($packs, $plans, $transaction), $pack, $context, $packs, $transaction];
}

it('R33: activates the pack when its payment is confirmed, valid until the same hour once the days of its offer pass', function () {
    [$activate, $pack, , $packs, $transaction] = activationWiring();

    $activated = $activate->execute(new ActivateDecantPackCommand($pack->id->value, '2026-11-10T15:05:00-06:00'));

    expect($activated->status)->toBe(DecantPackStatus::Active)
        ->and($activated->expiresAt)->toEqual(new DateTimeImmutable('2027-05-09T15:05:00-06:00'))
        ->and($activated->remainingDecants)->toBe(5)
        ->and($packs->find($pack->id)?->status)->toBe(DecantPackStatus::Active)
        // The pack is locked before it changes, so a second confirmation waits and then finds it active.
        ->and($packs->lockedIds)->toBe([$pack->id->value])
        ->and($transaction->committed())->toBe(1);
});

it('refuses to activate a pack that no longer waits for its payment', function () {
    [$activate, $pack] = activationWiring();
    $activate->execute(new ActivateDecantPackCommand($pack->id->value, '2026-11-10T15:05:00-06:00'));

    expect(fn () => $activate->execute(new ActivateDecantPackCommand($pack->id->value, '2026-11-10T15:06:00-06:00')))
        ->toThrow(DecantPackConflictException::class, 'packs.transition')
        ->and($pack->expiresAt)->toEqual(new DateTimeImmutable('2027-05-09T15:05:00-06:00'));
});

it('finds no pack of another business or with an identifier that is not one of a pack', function (Closure $packOf) {
    [$activate, $pack, $context] = activationWiring();

    expect(fn () => $activate->execute(new ActivateDecantPackCommand($packOf($pack, $context), '2026-11-10T15:05:00-06:00')))
        ->toThrow(DecantPackNotFoundException::class, 'packs.pack_not_found')
        ->and($pack->status)->toBe(DecantPackStatus::PendingPayment);
})->with([
    'a pack of another business' => [function (DecantPack $pack, FixedBusinessContext $context): string {
        $context->switchTo(new BusinessId('0192f0c4-ffff-7000-9000-000000000002'));

        return $pack->id->value;
    }],
    'a pack that does not exist' => [fn (DecantPack $pack, FixedBusinessContext $context): string => '0192f0c4-ffff-7000-9000-000000009999'],
    'an identifier that is not one of a pack' => [fn (DecantPack $pack, FixedBusinessContext $context): string => 'not-a-pack'],
]);
