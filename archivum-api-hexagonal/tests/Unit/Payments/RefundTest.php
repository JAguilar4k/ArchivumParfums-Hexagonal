<?php

declare(strict_types=1);

use Src\Payments\Payments\Domain\Entities\Refund;
use Src\Shared\Domain\Exceptions\InvalidValueException;

function registeredRefund(): Refund
{
    return Refund::create('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a61', 7500);
}

it('R40: starts a refund once, with the amount the provider charged', function () {
    $refund = registeredRefund();

    $refund->start(1491);

    expect($refund->attempted)->toBeTrue()->and($refund->chargedAmount)->toBe(1491)->and($refund->status)->toBe('pending');
    expect(fn () => $refund->start(1491))->toThrow(InvalidValueException::class, 'payments.invalid_refund');
});

it('refuses to start a refund without a charged amount or one that is no longer pending', function () {
    expect(fn () => registeredRefund()->start(0))->toThrow(InvalidValueException::class);

    $refund = registeredRefund();
    $refund->record(null, 'failed');
    expect(fn () => $refund->start(7500))->toThrow(InvalidValueException::class);
});

it('R40: keeps the reference of the provider and the state it answers', function () {
    $refund = registeredRefund();
    $refund->start(7500);

    $refund->record('refund-1', 'pending');
    $refund->record('refund-1', 'succeeded');

    expect($refund->providerReference)->toBe('refund-1')->and($refund->status)->toBe('succeeded');
});

it('keeps a refusal as a failed refund without a reference of the provider', function () {
    $refund = registeredRefund();
    $refund->start(7500);

    $refund->record(null, 'failed');

    expect($refund->status)->toBe('failed')->and($refund->providerReference)->toBeNull();
});

it('refuses an answer that changes the reference, comes without one or reaches a refund that is no longer pending', function (string $first, ?string $reference, string $status) {
    $refund = registeredRefund();
    $refund->start(7500);
    $refund->record('refund-1', $first);

    expect(fn () => $refund->record($reference, $status))->toThrow(InvalidValueException::class, 'payments.invalid_refund');
})->with([
    'another reference' => ['pending', 'refund-2', 'succeeded'],
    'a confirmation without a reference' => ['pending', null, 'succeeded'],
    'an unknown state' => ['pending', 'refund-1', 'refunded'],
    'a confirmed refund' => ['succeeded', 'refund-1', 'failed'],
    'a failed refund' => ['failed', 'refund-1', 'succeeded'],
]);

it('refuses a refund without colones, with an unknown state or attempted without its charged amount', function (int $amount, string $status, bool $attempted, ?int $chargedAmount) {
    expect(fn () => Refund::reconstitute('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a61', $amount, null, $status, null, $attempted, $chargedAmount))
        ->toThrow(InvalidValueException::class);
})->with([
    'no colones' => [0, 'pending', false, null],
    'an unknown state' => [7500, 'refunded', false, null],
    'attempted without its charged amount' => [7500, 'pending', true, null],
    'a charged amount of zero' => [7500, 'pending', true, 0],
]);
