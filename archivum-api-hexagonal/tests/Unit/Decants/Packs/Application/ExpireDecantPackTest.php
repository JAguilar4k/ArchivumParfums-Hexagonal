<?php

declare(strict_types=1);

use Src\Decants\Packs\Application\DTOs\ExpireDecantPackCommand;
use Src\Decants\Packs\Application\UseCases\ExpireDecantPack;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantPackConflictException;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\FakePackPayments;
use Tests\Support\Decants\InMemoryDecantPackPlanRepository;
use Tests\Support\Decants\InMemoryDecantPackRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

const EXPIRE_PACK_BUSINESS = '0192f0c4-1590-7000-9000-000000000001';

/** The moment the pack of these tests is paid; its 180 days of validity end on 2027-05-09T15:05:00-06:00. */
const EXPIRE_PACK_PAID_AT = '2026-11-10T15:05:00-06:00';

/**
 * Wire the use case to in-memory adapters that hold a pack of 5 decants of 5 ml and 180 days, bought at 15:00 of
 * 10/11/2026 and waiting for its payment until 15:30.
 *
 * @return array{ExpireDecantPack, DecantPack, FixedBusinessContext, InMemoryDecantPackRepository, FakePackPayments, RecordingTransactionRunner}
 */
function packExpiryWiring(): array
{
    $context = new FixedBusinessContext(new BusinessId(EXPIRE_PACK_BUSINESS));
    $plans = new InMemoryDecantPackPlanRepository($context);
    $plan = DecantPackPlan::create($plans->nextId(), $context->current(), new PlanName('Descubrimiento'), new DecantCount(5),
        new MlPerDecant(5), new PackPrice(40000), new ValidityDays(180), new MaxPricePerMl(2500));
    $packs = new InMemoryDecantPackRepository($context);
    $pack = DecantPack::create($packs->nextId(), '0192f0c4-1590-7000-9000-000000000301', $plan, new DateTimeImmutable('2026-11-10T15:30:00-06:00'));
    $packs->add($pack);
    $payments = new FakePackPayments;
    $transaction = new RecordingTransactionRunner;

    return [new ExpireDecantPack($packs, $payments, $transaction), $pack, $context, $packs, $payments, $transaction];
}

/** Activate the pack as its confirmed payment does, redeem $redeemed of its decants and store it. */
function storePaidPack(InMemoryDecantPackRepository $packs, DecantPack $pack, int $redeemed = 0): void
{
    $pack->activate(new DateTimeImmutable(EXPIRE_PACK_PAID_AT), new ValidityDays(180));
    for ($i = 0; $i < $redeemed; $i++) {
        $pack->redeem(5, 120000, 100, new DateTimeImmutable('2026-12-01T10:00:00-06:00'));
    }
    $packs->save($pack);
}

it('R33: expires a pack that nobody paid at its deadline, together with its pending payment', function () {
    [$expire, $pack, , $packs, $payments, $transaction] = packExpiryWiring();

    $expired = $expire->execute(new ExpireDecantPackCommand($pack->id->value, 'unpaid', '2026-11-10T15:30:00-06:00'));

    expect($expired)->toBeTrue()
        ->and($packs->find($pack->id)?->status)->toBe(DecantPackStatus::Expired)
        ->and($pack->remainingDecants)->toBe(5)
        ->and($payments->expired)->toBe([$pack->id->value])
        // The pack is locked before it changes, so a confirmation of its payment waits for this transaction.
        ->and($packs->lockedIds)->toBe([$pack->id->value])
        ->and($transaction->committed())->toBe(1);
});

it('R35: expires an active pack when its validity ends, even with decants left, and leaves its payments alone', function () {
    [$expire, $pack, , $packs, $payments, $transaction] = packExpiryWiring();
    storePaidPack($packs, $pack, redeemed: 2);

    $expired = $expire->execute(new ExpireDecantPackCommand($pack->id->value, 'validity_ended', '2027-05-09T15:05:00-06:00'));

    expect($expired)->toBeTrue()
        ->and($packs->find($pack->id)?->status)->toBe(DecantPackStatus::Expired)
        ->and($pack->remainingDecants)->toBe(3)
        ->and($payments->expired)->toBe([])
        ->and($transaction->committed())->toBe(1);
});

it('leaves the pack as it is before its deadline, and once it no longer has the status its reason expires', function (Closure $prepare, string $reason, string $checkedAt, DecantPackStatus $status) {
    [$expire, $pack, , $packs, $payments] = packExpiryWiring();
    $prepare($packs, $pack);

    $expired = $expire->execute(new ExpireDecantPackCommand($pack->id->value, $reason, $checkedAt));

    expect($expired)->toBeFalse()
        ->and($pack->status)->toBe($status)
        ->and($payments->expired)->toBe([]);
})->with([
    'unpaid, one second before its deadline' => [fn () => null, 'unpaid', '2026-11-10T15:29:59-06:00', DecantPackStatus::PendingPayment],
    'active, one second before its validity ends' => [fn (InMemoryDecantPackRepository $packs, DecantPack $pack) => storePaidPack($packs, $pack), 'validity_ended', '2027-05-09T15:04:59-06:00', DecantPackStatus::Active],
    'exhausted, after its validity ends' => [fn (InMemoryDecantPackRepository $packs, DecantPack $pack) => storePaidPack($packs, $pack, redeemed: 5), 'validity_ended', '2027-05-10T00:00:00-06:00', DecantPackStatus::Exhausted],
    'already expired' => [function (InMemoryDecantPackRepository $packs, DecantPack $pack): void {
        $pack->expire(new DateTimeImmutable('2026-11-10T15:30:00-06:00'));
        $packs->save($pack);
    }, 'unpaid', '2026-11-10T15:31:00-06:00', DecantPackStatus::Expired],
    // Each job expires only the packs of its reason: the job of the validity expires this one.
    'paid, when the job of its payment deadline runs after its validity' => [fn (InMemoryDecantPackRepository $packs, DecantPack $pack) => storePaidPack($packs, $pack), 'unpaid', '2027-05-10T00:00:00-06:00', DecantPackStatus::Active],
    'unpaid, when a job of the validity runs after its deadline' => [fn () => null, 'validity_ended', '2026-11-10T16:00:00-06:00', DecantPackStatus::PendingPayment],
]);

it('gives the same result when its job runs twice', function () {
    [$expire, $pack, , $packs, $payments] = packExpiryWiring();
    $expire->execute(new ExpireDecantPackCommand($pack->id->value, 'unpaid', '2026-11-10T15:30:00-06:00'));

    $again = $expire->execute(new ExpireDecantPackCommand($pack->id->value, 'unpaid', '2026-11-10T15:31:00-06:00'));

    expect($again)->toBeFalse()
        ->and($packs->find($pack->id)?->status)->toBe(DecantPackStatus::Expired)
        ->and($payments->expired)->toBe([$pack->id->value]);
});

it('finds no pack of another business or one that does not exist', function (Closure $packOf) {
    [$expire, $pack, $context, , $payments] = packExpiryWiring();

    expect($expire->execute(new ExpireDecantPackCommand($packOf($pack, $context), 'unpaid', '2026-11-10T15:30:00-06:00')))->toBeFalse()
        ->and($pack->status)->toBe(DecantPackStatus::PendingPayment)
        ->and($payments->expired)->toBe([]);
})->with([
    'a pack of another business' => [function (DecantPack $pack, FixedBusinessContext $context): string {
        $context->switchTo(new BusinessId('0192f0c4-1590-7000-9000-000000000002'));

        return $pack->id->value;
    }],
    'a pack that does not exist' => [fn (DecantPack $pack, FixedBusinessContext $context): string => '0192f0c4-1590-7000-9000-000000009999'],
]);

it('undoes the expiry when the row of the pack changed after it was read, together with the payment it closed', function () {
    [$expire, $pack, , $packs, , $transaction] = packExpiryWiring();
    // The confirmation of its payment committed after this operation read the pack.
    $packs->changeStoredStatus($pack->id, DecantPackStatus::Active);

    expect(fn () => $expire->execute(new ExpireDecantPackCommand($pack->id->value, 'unpaid', '2026-11-10T15:30:00-06:00')))
        ->toThrow(DecantPackConflictException::class, 'packs.transition')
        ->and($transaction->rolledBack())->toBe(1)
        ->and($transaction->committed())->toBe(0);
});
