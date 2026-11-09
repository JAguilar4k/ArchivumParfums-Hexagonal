<?php

declare(strict_types=1);

use Src\Decants\Packs\Application\DTOs\RedeemPackDecantCommand;
use Src\Decants\Packs\Application\UseCases\RedeemPackDecant;
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
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\InMemoryDecantPackPlanRepository;
use Tests\Support\Decants\InMemoryDecantPackRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

const REDEEM_PACK_BUSINESS = '0192f0c4-aaaa-7000-9000-000000000001';
const REDEEM_PACK_CUSTOMER = '0192f0c4-aaaa-7000-9000-000000000301';

/**
 * Wire the use case to in-memory adapters that hold a pack of 2 decants of 5 ml with a cap of ₡2 500 per ml, paid at
 * 15:05 of 10/11/2026 and valid for 180 days, or one that still waits for its payment.
 *
 * @return array{RedeemPackDecant, DecantPack, FixedBusinessContext, InMemoryDecantPackRepository, RecordingTransactionRunner}
 */
function redemptionWiring(bool $paid = true): array
{
    $context = new FixedBusinessContext(new BusinessId(REDEEM_PACK_BUSINESS));
    $plans = new InMemoryDecantPackPlanRepository($context);
    $plan = DecantPackPlan::create($plans->nextId(), $context->current(), new PlanName('Descubrimiento'), new DecantCount(2),
        new MlPerDecant(5), new PackPrice(18000), new ValidityDays(180), new MaxPricePerMl(2500));
    $packs = new InMemoryDecantPackRepository($context);
    $pack = DecantPack::create($packs->nextId(), REDEEM_PACK_CUSTOMER, $plan, new DateTimeImmutable('2026-11-10T15:30:00-06:00'));
    if ($paid) {
        $pack->activate(new DateTimeImmutable('2026-11-10T15:05:00-06:00'), new ValidityDays(180));
    }
    $packs->add($pack);
    $transaction = new RecordingTransactionRunner;

    return [new RedeemPackDecant($packs, $transaction), $pack, $context, $packs, $transaction];
}

/** A decant of 5 ml of a 100 ml presentation sold at ₡120 000 (₡1 200 per ml), redeemed on 01/12/2026. */
function redemptionOf(string $packId, int $ml = 5, int $salePrice = 120000, string $customerId = REDEEM_PACK_CUSTOMER, string $at = '2026-12-01T10:00:00-06:00'): RedeemPackDecantCommand
{
    return new RedeemPackDecantCommand($packId, $customerId, $ml, $salePrice, 100, $at);
}

it('R34: takes one decant of an active pack of the customer, locked until the transaction of the order ends', function () {
    [$redeem, $pack, , $packs, $transaction] = redemptionWiring();

    $redeem->execute(redemptionOf($pack->id->value));

    expect($packs->find($pack->id)?->remainingDecants)->toBe(1)
        ->and($pack->status)->toBe(DecantPackStatus::Active)
        // The pack is locked before it changes, so a second redemption waits and then sees the decant this one left.
        ->and($packs->lockedIds)->toBe([$pack->id->value])
        ->and($transaction->committed())->toBe(1);

    $redeem->execute(redemptionOf($pack->id->value));

    expect($pack->remainingDecants)->toBe(0)
        ->and($pack->status)->toBe(DecantPackStatus::Exhausted)
        ->and(fn () => $redeem->execute(redemptionOf($pack->id->value)))->toThrow(DecantPackConflictException::class, 'packs.exhausted')
        ->and($pack->remainingDecants)->toBe(0);
});

it('R34: refuses a decant the pack does not cover and leaves the pack as it was', function (int $ml, int $salePrice, string $key) {
    [$redeem, $pack, , , $transaction] = redemptionWiring();

    expect(fn () => $redeem->execute(redemptionOf($pack->id->value, $ml, $salePrice)))->toThrow(InvalidValueException::class, $key)
        ->and($pack->remainingDecants)->toBe(2)
        ->and($transaction->rolledBack())->toBe(1);
})->with([
    'fewer ml than the pack' => [2, 120000, 'packs.ml_mismatch'],
    'more ml than the pack' => [10, 120000, 'packs.ml_mismatch'],
    'a presentation above the cap' => [5, 250100, 'packs.over_price_cap'],
]);

it('R35: refuses a pack that waits for its payment or whose validity ended', function () {
    [$redeem, $pack] = redemptionWiring(paid: false);
    [$redeemActive, $active] = redemptionWiring();

    expect(fn () => $redeem->execute(redemptionOf($pack->id->value, at: '2026-11-10T15:10:00-06:00')))
        ->toThrow(DecantPackConflictException::class, 'packs.not_active')
        ->and(fn () => $redeemActive->execute(redemptionOf($active->id->value, at: '2027-05-09T15:05:00-06:00')))
        ->toThrow(DecantPackConflictException::class, 'packs.expired')
        ->and($pack->remainingDecants)->toBe(2)
        ->and($active->remainingDecants)->toBe(2);
});

it('finds no pack of another customer, of another business or with an identifier that is not one of a pack', function (Closure $commandFor) {
    [$redeem, $pack, $context] = redemptionWiring();

    expect(fn () => $redeem->execute($commandFor($pack, $context)))
        ->toThrow(DecantPackNotFoundException::class, 'packs.pack_not_found')
        ->and($pack->remainingDecants)->toBe(2);
})->with([
    'a pack of another customer' => [fn (DecantPack $pack): RedeemPackDecantCommand => redemptionOf($pack->id->value, customerId: '0192f0c4-aaaa-7000-9000-000000000302')],
    'a pack of another business' => [function (DecantPack $pack, FixedBusinessContext $context): RedeemPackDecantCommand {
        $context->switchTo(new BusinessId('0192f0c4-aaaa-7000-9000-000000000002'));

        return redemptionOf($pack->id->value);
    }],
    'a pack that does not exist' => [fn (): RedeemPackDecantCommand => redemptionOf('0192f0c4-aaaa-7000-9000-000000009999')],
    'an identifier that is not one of a pack' => [fn (): RedeemPackDecantCommand => redemptionOf('not-a-pack')],
]);
