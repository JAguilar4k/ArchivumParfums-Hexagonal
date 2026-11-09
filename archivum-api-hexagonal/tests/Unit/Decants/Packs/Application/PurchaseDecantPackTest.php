<?php

declare(strict_types=1);

use Src\Decants\Packs\Application\DTOs\PurchaseDecantPackCommand;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantPackPlanNotFoundException;
use Src\Decants\Packs\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Decants\LatePackKeyLookup;
use Tests\Support\Decants\PackPurchaseWiring;

const PURCHASE_PACK_OTHER_BUSINESS = '0192f0c4-eeee-7000-9000-000000000002';
const PURCHASE_PACK_USER = '0192f0c4-eeee-7000-9000-000000000011';
const PURCHASE_PACK_OTHER_USER = '0192f0c4-eeee-7000-9000-000000000012';
const PURCHASE_PACK_AT = '2026-11-10T15:00:00-06:00';

/** The purchase of an offer by an account of the store at 15:00 of 10/11/2026, by card unless said otherwise. */
function purchaseOf(string $planId, ?string $key = null, string $userId = PURCHASE_PACK_USER, string $method = 'card', string $at = PURCHASE_PACK_AT): PurchaseDecantPackCommand
{
    return new PurchaseDecantPackCommand($planId, $userId, $key, $method, $at);
}

it('R33: buys a pack of an offer on sale that waits for its payment, and then starts the payment of its full price', function () {
    $wiring = PackPurchaseWiring::create();

    $purchased = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, 'compra-1', method: 'sinpe_mobile'));

    expect($purchased->created)->toBeTrue()
        ->and($purchased->pack->status)->toBe(DecantPackStatus::PendingPayment)
        ->and($purchased->pack->businessId->value)->toBe(PackPurchaseWiring::BUSINESS)
        ->and($purchased->pack->customerId)->toBe($wiring->customers->customers[PURCHASE_PACK_USER])
        ->and($purchased->pack->planId->value)->toBe($wiring->plan->id->value)
        ->and([$purchased->pack->totalDecants, $purchased->pack->remainingDecants, $purchased->pack->mlPerDecant, $purchased->pack->maxPricePerMl?->amount])->toBe([5, 5, 5, 2500])
        ->and($purchased->pack->idempotencyKey?->value)->toBe('compra-1')
        ->and($purchased->payment?->checkoutUrl)->toBe('https://checkout.onvopay.com/pay/'.$purchased->pack->id->value)
        ->and($wiring->packs->all())->toBe([$purchased->pack])
        ->and($wiring->payments->started)->toBe([['pack' => $purchased->pack->id->value, 'user' => PURCHASE_PACK_USER, 'method' => 'sinpe_mobile']])
        // The pack commits in its own transaction; the payment then opens its own ones, outside of it.
        ->and($wiring->transaction->runs())->toBe(1)
        ->and($wiring->transaction->committed())->toBe(1);
});

it('R33: lets the pack wait for its payment as many minutes as the store gives an unpaid purchase', function (int $minutes, string $deadline) {
    $wiring = PackPurchaseWiring::create($minutes);

    $purchased = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value));

    expect($purchased->pack->expiresAt)->toEqual(new DateTimeImmutable($deadline));
})->with([
    'the 30 minutes of every store' => [30, '2026-11-10T15:30:00-06:00'],
    'the 45 minutes of a store that changed them' => [45, '2026-11-10T15:45:00-06:00'],
]);

it('makes the account a customer of the store with its first purchase there', function () {
    $wiring = PackPurchaseWiring::create();

    $first = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value));
    $second = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, userId: PURCHASE_PACK_OTHER_USER));

    expect(array_keys($wiring->customers->customers))->toBe([PURCHASE_PACK_USER, PURCHASE_PACK_OTHER_USER])
        ->and($first->pack->customerId)->not->toBe($second->pack->customerId);
});

it('answers the same pack and its payment when the purchase is sent again with the same key, without a second pack', function () {
    $wiring = PackPurchaseWiring::create();
    $first = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, 'compra-1'));

    $again = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, 'compra-1'));

    expect($again->created)->toBeFalse()
        ->and($again->pack->id->value)->toBe($first->pack->id->value)
        ->and($again->payment)->toBe($first->payment)
        ->and($wiring->packs->added)->toBe(1);
});

it('answers the pack of the request that won the race when two purchases with the same key arrive at once', function () {
    $wiring = PackPurchaseWiring::create();
    $winner = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, 'compra-1'));
    $racing = $wiring->withPacks(new LatePackKeyLookup($wiring->packs));

    $loser = $racing->execute(purchaseOf($wiring->plan->id->value, 'compra-1'));

    expect($loser->created)->toBeFalse()
        ->and($loser->pack->id->value)->toBe($winner->pack->id->value)
        ->and($loser->payment)->toBe($winner->payment)
        ->and($wiring->packs->added)->toBe(1)
        // The insert of the loser rolled back its transaction; the winner was read in a new one.
        ->and($wiring->transaction->rolledBack())->toBe(1)
        ->and($wiring->transaction->runs())->toBe(3);
});

it('refuses the same key for another offer, and buys nothing', function () {
    $wiring = PackPurchaseWiring::create();
    $other = DecantPackPlan::create($wiring->plans->nextId(), $wiring->context->current(), new PlanName('Diez de 2 ml'), new DecantCount(10),
        new MlPerDecant(2), new PackPrice(25000), new ValidityDays(90), null);
    $wiring->plans->save($other);
    $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, 'compra-1'));

    expect(fn () => $wiring->purchase->execute(purchaseOf($other->id->value, 'compra-1')))
        ->toThrow(IdempotencyKeyReusedException::class, 'packs.idempotency_key_reused')
        ->and($wiring->packs->added)->toBe(1)
        ->and($wiring->payments->started)->toHaveCount(1);
});

it('buys a new pack with another key or without one, even with a pack of the same offer still unpaid', function (?string $firstKey, ?string $secondKey) {
    $wiring = PackPurchaseWiring::create();

    $first = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, $firstKey));
    $second = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, $secondKey));

    expect($second->created)->toBeTrue()
        ->and($second->pack->id->value)->not->toBe($first->pack->id->value)
        ->and($wiring->packs->added)->toBe(2)
        ->and($wiring->payments->started)->toHaveCount(2);
})->with([
    'two keys' => ['compra-1', 'compra-2'],
    'a key and then none' => ['compra-1', null],
    'none twice' => [null, null],
]);

it('keeps the keys of each customer apart: another account with the same key buys its own pack', function () {
    $wiring = PackPurchaseWiring::create();

    $first = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, 'compra-1'));
    $second = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, 'compra-1', PURCHASE_PACK_OTHER_USER));

    expect($second->created)->toBeTrue()
        ->and($second->pack->id->value)->not->toBe($first->pack->id->value);
});

it('answers the last payment of a pack that no longer waits for it, without starting another', function (Closure $change) {
    $wiring = PackPurchaseWiring::create();
    $first = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, 'compra-1'));
    $change($first->pack);

    $again = $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, 'compra-1', at: '2026-11-10T15:40:00-06:00'));

    expect($again->created)->toBeFalse()
        ->and($again->payment)->toBe($first->payment)
        ->and($wiring->payments->started)->toHaveCount(1);
})->with([
    'it was paid' => [fn (DecantPack $pack) => $pack->activate(new DateTimeImmutable('2026-11-10T15:05:00-06:00'), new ValidityDays(180))],
    'its deadline passed' => [fn (DecantPack $pack) => null],
]);

it('R32: buys only an offer on sale of the store of the address', function (Closure $planOf) {
    $wiring = PackPurchaseWiring::create();

    expect(fn () => $wiring->purchase->execute(purchaseOf($planOf($wiring))))
        ->toThrow(DecantPackPlanNotFoundException::class, 'packs.plan_not_found')
        ->and($wiring->packs->added)->toBe(0)
        ->and($wiring->payments->started)->toBe([]);
})->with([
    'an offer taken off sale' => [function (PackPurchaseWiring $wiring): string {
        $wiring->plan->deactivate();

        return $wiring->plan->id->value;
    }],
    'an offer of another store' => [function (PackPurchaseWiring $wiring): string {
        $wiring->context->switchTo(new BusinessId(PURCHASE_PACK_OTHER_BUSINESS));

        return $wiring->plan->id->value;
    }],
    'an offer that does not exist' => [fn (PackPurchaseWiring $wiring): string => '0192f0c4-eeee-7000-9000-000000009999'],
    'an identifier that is not one of an offer' => [fn (PackPurchaseWiring $wiring): string => 'not-an-offer'],
]);

it('refuses a key that is blank or longer than 100 characters before it buys anything', function (string $key) {
    $wiring = PackPurchaseWiring::create();

    expect(fn () => $wiring->purchase->execute(purchaseOf($wiring->plan->id->value, $key)))
        ->toThrow(InvalidValueException::class, 'packs.idempotency_key_invalid')
        ->and($wiring->packs->added)->toBe(0);
})->with([
    'blank' => [' '],
    'too long' => [str_repeat('k', 101)],
]);
