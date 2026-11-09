<?php

declare(strict_types=1);

use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantPackConflictException;
use Src\Decants\Packs\Domain\Exceptions\DecantPackPlanNotFoundException;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackPlanId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Domain\ValueObjects\IdempotencyKey;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;

/** An offer of 5 ml decants, on sale unless said otherwise. */
function offerOfThePack(int $decants = 5, ?int $cap = 2500, bool $onSale = true): DecantPackPlan
{
    return DecantPackPlan::reconstitute(new DecantPackPlanId('0192f0c4-0000-7000-9000-000000000101'),
        new BusinessId('0192f0c4-0000-7000-9000-000000000002'), new PlanName('Descubrimiento'),
        new DecantCount($decants), new MlPerDecant(5), new PackPrice(40000), new ValidityDays(180),
        $cap === null ? null : new MaxPricePerMl($cap), $onSale);
}

/** A pack bought from the offer at 15:00 of 10/11/2026, which waits for its payment until 15:30. */
function boughtPack(?DecantPackPlan $offer = null): DecantPack
{
    return DecantPack::create(new DecantPackId('0192f0c4-0000-7000-9000-000000000201'),
        '0192f0c4-0000-7000-9000-000000000301', $offer ?? offerOfThePack(), new DateTimeImmutable('2026-11-10T15:30:00-06:00'));
}

/** A pack whose payment was confirmed at 15:05 of 10/11/2026, valid for 180 days. */
function activePack(?DecantPackPlan $offer = null): DecantPack
{
    $pack = boughtPack($offer);
    $pack->activate(new DateTimeImmutable('2026-11-10T15:05:00-06:00'), new ValidityDays(180));

    return $pack;
}

/** A stored pack of 5 decants of 5 ml with a cap of ₡2 500 per ml. */
function storedPack(DecantPackStatus $status, int $remaining, string $expiresAt = '2027-05-09T15:05:00-06:00', int $total = 5, int $ml = 5): DecantPack
{
    return DecantPack::reconstitute(new DecantPackId('0192f0c4-0000-7000-9000-000000000202'),
        new BusinessId('0192f0c4-0000-7000-9000-000000000002'), '0192f0c4-0000-7000-9000-000000000301',
        new DecantPackPlanId('0192f0c4-0000-7000-9000-000000000101'), $total, $remaining, $ml, new MaxPricePerMl(2500),
        $status, new DateTimeImmutable($expiresAt));
}

it('R33: buys a pack that waits for its payment, with the decants, the ml and the cap of the offer', function () {
    $pack = boughtPack();

    expect($pack->status)->toBe(DecantPackStatus::PendingPayment)
        ->and($pack->businessId->value)->toBe('0192f0c4-0000-7000-9000-000000000002')
        ->and($pack->customerId)->toBe('0192f0c4-0000-7000-9000-000000000301')
        ->and($pack->planId->value)->toBe('0192f0c4-0000-7000-9000-000000000101')
        ->and($pack->totalDecants)->toBe(5)
        ->and($pack->remainingDecants)->toBe(5)
        ->and($pack->mlPerDecant)->toBe(5)
        ->and($pack->maxPricePerMl?->amount)->toBe(2500)
        ->and($pack->expiresAt->format(DATE_ATOM))->toBe('2026-11-10T15:30:00-06:00')
        ->and(boughtPack(offerOfThePack(cap: null))->maxPricePerMl)->toBeNull();
});

it('R33: buys no pack from an offer that is off sale', function () {
    expect(fn () => boughtPack(offerOfThePack(onSale: false)))
        ->toThrow(DecantPackPlanNotFoundException::class, 'packs.plan_not_found');
});

it('R33: activates the pack when its payment is confirmed, and its validity counts from that moment', function () {
    $pack = activePack();

    expect($pack->status)->toBe(DecantPackStatus::Active)
        ->and($pack->remainingDecants)->toBe(5)
        ->and($pack->expiresAt->format(DATE_ATOM))->toBe('2027-05-09T15:05:00-06:00')
        ->and(fn () => $pack->activate(new DateTimeImmutable('2026-11-10T15:06:00-06:00'), new ValidityDays(180)))
        ->toThrow(DecantPackConflictException::class, 'packs.transition');
});

it('R33: expires a pack that is not paid by its deadline', function () {
    $pack = boughtPack();
    $before = new DateTimeImmutable('2026-11-10T15:29:59-06:00');
    $deadline = new DateTimeImmutable('2026-11-10T15:30:00-06:00');

    expect($pack->isOverdueAt($before))->toBeFalse()
        ->and($pack->statusAt($before))->toBe(DecantPackStatus::PendingPayment)
        ->and(fn () => $pack->expire($before))->toThrow(DecantPackConflictException::class, 'packs.transition')
        ->and($pack->isOverdueAt($deadline))->toBeTrue()
        ->and($pack->statusAt($deadline))->toBe(DecantPackStatus::Expired);

    $pack->expire($deadline);

    expect($pack->status)->toBe(DecantPackStatus::Expired)
        ->and($pack->remainingDecants)->toBe(5)
        ->and(fn () => $pack->activate($deadline, new ValidityDays(180)))->toThrow(DecantPackConflictException::class, 'packs.transition');
});

it('R34: each redemption takes one decant of the pack, and the last one exhausts it', function () {
    $pack = activePack(offerOfThePack(decants: 2));
    $at = new DateTimeImmutable('2026-12-01T10:00:00-06:00');

    $pack->redeem(5, 98000, 50, $at);

    expect($pack->remainingDecants)->toBe(1)
        ->and($pack->status)->toBe(DecantPackStatus::Active);

    $pack->redeem(5, 120000, 100, $at);

    expect($pack->remainingDecants)->toBe(0)
        ->and($pack->status)->toBe(DecantPackStatus::Exhausted)
        ->and(fn () => $pack->redeem(5, 120000, 100, $at))->toThrow(DecantPackConflictException::class, 'packs.exhausted')
        ->and($pack->remainingDecants)->toBe(0);
});

it('R34: redeems only a decant of exactly the ml of the pack, from a presentation that fits under its cap', function () {
    $pack = activePack();
    $at = new DateTimeImmutable('2026-12-01T10:00:00-06:00');

    expect(fn () => $pack->redeem(2, 98000, 50, $at))->toThrow(InvalidValueException::class, 'packs.ml_mismatch')
        ->and(fn () => $pack->redeem(10, 98000, 50, $at))->toThrow(InvalidValueException::class, 'packs.ml_mismatch')
        ->and(fn () => $pack->redeem(5, 135000, 50, $at))->toThrow(InvalidValueException::class, 'packs.over_price_cap')
        ->and($pack->remainingDecants)->toBe(5);

    $pack->redeem(5, 250000, 100, $at);
    $uncapped = activePack(offerOfThePack(cap: null));
    $uncapped->redeem(5, 210000, 50, $at);

    expect($pack->remainingDecants)->toBe(4)
        ->and($uncapped->remainingDecants)->toBe(4);
});

it('R35: does not redeem a pack that is expired or exhausted, nor one that waits for its payment', function (Closure $pack, string $key) {
    /** @var DecantPack $pack */
    $pack = $pack();
    $remaining = $pack->remainingDecants;

    expect(fn () => $pack->redeem(5, 98000, 50, new DateTimeImmutable('2026-11-10T15:10:00-06:00')))
        ->toThrow(DecantPackConflictException::class, $key)
        ->and($pack->remainingDecants)->toBe($remaining);
})->with([
    'waiting for its payment' => [fn () => boughtPack(), 'packs.not_active'],
    'unpaid after its deadline' => [fn () => storedPack(DecantPackStatus::PendingPayment, 5, '2026-11-10T15:10:00-06:00'), 'packs.expired'],
    'active after its validity, before the queue expires it' => [fn () => storedPack(DecantPackStatus::Active, 3, '2026-11-10T15:10:00-06:00'), 'packs.expired'],
    'expired with decants left' => [fn () => storedPack(DecantPackStatus::Expired, 3), 'packs.expired'],
    'exhausted' => [fn () => storedPack(DecantPackStatus::Exhausted, 0), 'packs.exhausted'],
]);

it('R35: expires an active pack at the end of its validity, even with decants left, but never an exhausted one', function () {
    $end = new DateTimeImmutable('2027-05-09T15:05:00-06:00');
    $pack = storedPack(DecantPackStatus::Active, 3);
    $exhausted = storedPack(DecantPackStatus::Exhausted, 0);

    expect($pack->isOverdueAt($end->modify('-1 second')))->toBeFalse()
        ->and($pack->statusAt($end))->toBe(DecantPackStatus::Expired);

    $pack->expire($end);

    expect($pack->status)->toBe(DecantPackStatus::Expired)
        ->and($pack->remainingDecants)->toBe(3)
        ->and(fn () => $pack->expire($end->modify('+1 day')))->toThrow(DecantPackConflictException::class, 'packs.transition')
        ->and($exhausted->isOverdueAt($end->modify('+1 day')))->toBeFalse()
        ->and($exhausted->statusAt($end->modify('+1 day')))->toBe(DecantPackStatus::Exhausted)
        ->and(fn () => $exhausted->expire($end->modify('+1 day')))->toThrow(DecantPackConflictException::class, 'packs.transition');
});

it('takes back the decant of a cancelled or expired order, and an exhausted pack is active again while it is valid', function () {
    $exhausted = storedPack(DecantPackStatus::Exhausted, 0);
    $active = storedPack(DecantPackStatus::Active, 3);

    $exhausted->returnDecant(new DateTimeImmutable('2027-05-09T15:04:59-06:00'));
    $active->returnDecant(new DateTimeImmutable('2026-12-01T10:00:00-06:00'));

    expect($exhausted->remainingDecants)->toBe(1)
        ->and($exhausted->status)->toBe(DecantPackStatus::Active)
        ->and($active->remainingDecants)->toBe(4)
        ->and($active->status)->toBe(DecantPackStatus::Active);

    $exhausted->redeem(5, 98000, 50, new DateTimeImmutable('2027-05-09T15:04:59-06:00'));

    expect($exhausted->status)->toBe(DecantPackStatus::Exhausted);
});

it('R35: takes back a decant into a pack whose validity ended, which stays expired and is never used again', function () {
    $end = new DateTimeImmutable('2027-05-09T15:05:00-06:00');
    $exhausted = storedPack(DecantPackStatus::Exhausted, 0);
    $expired = storedPack(DecantPackStatus::Expired, 2);

    $exhausted->returnDecant($end);
    $expired->returnDecant($end->modify('+1 day'));

    expect($exhausted->remainingDecants)->toBe(1)
        ->and($exhausted->status)->toBe(DecantPackStatus::Expired)
        ->and($expired->remainingDecants)->toBe(3)
        ->and($expired->status)->toBe(DecantPackStatus::Expired)
        ->and(fn () => $expired->redeem(5, 98000, 50, $end->modify('+1 day')))->toThrow(DecantPackConflictException::class, 'packs.expired');
});

it('R35: offers no operation to refund a pack or to change it for money', function () {
    $operations = array_map(
        fn (ReflectionMethod $method): string => $method->getName(),
        new ReflectionClass(DecantPack::class)->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    // repeatsPurchase only compares a key (#145): it changes nothing of the pack. returnDecant gives a decant back to
    // the pack, never money (#146).
    expect($operations)->toEqualCanonicalizing(['create', 'reconstitute', 'repeatsPurchase', 'activate', 'isOverdueAt', 'expire', 'statusAt', 'redeem', 'returnDecant']);
});

it('keeps the key of its purchase and tells it apart from the key of another purchase or another offer', function () {
    $pack = DecantPack::create(new DecantPackId('0192f0c4-0000-7000-9000-000000000201'), '0192f0c4-0000-7000-9000-000000000301',
        offerOfThePack(), new DateTimeImmutable('2026-11-10T15:30:00-06:00'), new IdempotencyKey('compra-1'));
    $offer = new DecantPackPlanId('0192f0c4-0000-7000-9000-000000000101');

    expect($pack->idempotencyKey?->value)->toBe('compra-1')
        ->and($pack->repeatsPurchase(new IdempotencyKey('compra-1'), $offer))->toBeTrue()
        ->and($pack->repeatsPurchase(new IdempotencyKey('compra-2'), $offer))->toBeFalse()
        ->and($pack->repeatsPurchase(new IdempotencyKey('compra-1'), new DecantPackPlanId('0192f0c4-0000-7000-9000-000000000102')))->toBeFalse()
        ->and(boughtPack()->idempotencyKey)->toBeNull()
        ->and(boughtPack()->repeatsPurchase(new IdempotencyKey('compra-1'), $offer))->toBeFalse();
});

it('refuses a key that is blank or longer than its column', function (string $key) {
    expect(fn () => new IdempotencyKey($key))->toThrow(InvalidValueException::class, 'packs.idempotency_key_invalid');
})->with([
    'empty' => [''],
    'only spaces' => ['   '],
    'one character too long' => [str_repeat('k', IdempotencyKey::MAX_LENGTH + 1)],
]);

it('accepts a key of up to 100 characters', function () {
    expect(new IdempotencyKey(str_repeat('k', 100))->value)->toHaveLength(100)
        ->and(new IdempotencyKey('k')->value)->toBe('k');
});

it('refuses the transitions that section 6 does not have', function (Closure $transition) {
    expect($transition)->toThrow(DecantPackConflictException::class, 'packs.transition');
})->with([
    'activate an active pack' => [fn () => storedPack(DecantPackStatus::Active, 5)->activate(new DateTimeImmutable('2026-11-10T15:06:00-06:00'), new ValidityDays(180))],
    'activate an exhausted pack' => [fn () => storedPack(DecantPackStatus::Exhausted, 0)->activate(new DateTimeImmutable('2026-11-10T15:06:00-06:00'), new ValidityDays(180))],
    'activate an expired pack' => [fn () => storedPack(DecantPackStatus::Expired, 5)->activate(new DateTimeImmutable('2026-11-10T15:06:00-06:00'), new ValidityDays(180))],
    'expire an active pack within its validity' => [fn () => storedPack(DecantPackStatus::Active, 5)->expire(new DateTimeImmutable('2027-05-09T15:04:59-06:00'))],
    'expire an expired pack' => [fn () => storedPack(DecantPackStatus::Expired, 5, '2026-11-10T15:30:00-06:00')->expire(new DateTimeImmutable('2026-11-11T15:30:00-06:00'))],
    'give a decant back to a pack that waits for its payment' => [fn () => boughtPack()->returnDecant(new DateTimeImmutable('2026-11-10T15:10:00-06:00'))],
    'give a decant back to a pack that has every decant it bought' => [fn () => storedPack(DecantPackStatus::Active, 5)->returnDecant(new DateTimeImmutable('2026-12-01T10:00:00-06:00'))],
    'give a decant back to an expired pack that has every decant it bought' => [fn () => storedPack(DecantPackStatus::Expired, 5)->returnDecant(new DateTimeImmutable('2027-05-10T10:00:00-06:00'))],
]);

it('refuses a stored pack whose decants left contradict its status', function (DecantPackStatus $status, int $remaining, int $total, int $ml) {
    expect(fn () => storedPack($status, $remaining, total: $total, ml: $ml))->toThrow(InvalidValueException::class, 'packs.invalid_pack');
})->with([
    'unpaid with a decant redeemed' => [DecantPackStatus::PendingPayment, 4, 5, 5],
    'active without decants' => [DecantPackStatus::Active, 0, 5, 5],
    'exhausted with a decant left' => [DecantPackStatus::Exhausted, 1, 5, 5],
    'more decants left than bought' => [DecantPackStatus::Active, 6, 5, 5],
    'a negative balance' => [DecantPackStatus::Expired, -1, 5, 5],
    'no decants bought' => [DecantPackStatus::Expired, 0, 0, 5],
    'decants without ml' => [DecantPackStatus::Active, 5, 5, 0],
]);

it('speaks the Spanish vocabulary of the API for each status', function () {
    expect(array_map(fn (DecantPackStatus $status): string => $status->spanish(), DecantPackStatus::cases()))
        ->toBe(['pendiente_de_pago', 'activo', 'agotado', 'vencido']);
});
