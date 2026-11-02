<?php

declare(strict_types=1);

use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeResult;
use Src\Payments\Payments\Domain\ValueObjects\ExchangeRate;
use Src\Payments\Payments\Domain\ValueObjects\ManualPaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\ManualRecord;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;
use Src\Shared\Domain\ValueObjects\MemberId;

function requestedPayment(int $amount = 7500, string $gateway = 'onvo'): Payment
{
    return Payment::create(new PaymentId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a61'), new BusinessId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62'), new Payable('order', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a63'), PaymentPurpose::Deposit, new Colones($amount), $gateway, PaymentMethod::Card);
}

/** What the owner records about a balance received on delivery. */
function deliveryRecord(string $reference = 'SINPE-2026-0001'): ManualRecord
{
    return new ManualRecord(new MemberId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64'), ManualPaymentMethod::Sinpe, $reference, new DateTimeImmutable('2026-11-02T10:30:00-06:00'));
}

/** A balance that the owner received on delivery and recorded by hand. */
function paymentReceivedOnDelivery(int $amount = 7500): Payment
{
    return Payment::recordManually(new PaymentId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a65'), new BusinessId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62'), new Payable('order', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a63'), new Colones($amount), deliveryRecord());
}

it('R36: creates a pending payment without confirming the resource', function () {
    $payment = requestedPayment();
    expect($payment->status)->toBe(PaymentStatus::Pending)->and($payment->providerReference)->toBeNull()->and($payment->amount->amount)->toBe(7500);
});

it('refuses zero charges and an unknown gateway', function (int $amount, string $gateway) {
    expect(fn () => requestedPayment($amount, $gateway))->toThrow(InvalidValueException::class);
})->with([[0, 'onvo'], [100, 'unknown']]);

it('applies each terminal status to a pending payment', function (PaymentStatus $status) {
    $payment = requestedPayment();
    $payment->apply($status);
    $payment->apply($status);
    expect($payment->status)->toBe($status);
})->with([PaymentStatus::Succeeded, PaymentStatus::Failed, PaymentStatus::Expired]);

it('refuses a terminal payment going back to pending or to another outcome', function (PaymentStatus $status, PaymentStatus $next) {
    $payment = requestedPayment();
    $payment->apply($status);
    expect(fn () => $payment->apply($next))->toThrow(InvalidPaymentTransitionException::class);
})->with([
    [PaymentStatus::Succeeded, PaymentStatus::Pending], [PaymentStatus::Succeeded, PaymentStatus::Failed],
    [PaymentStatus::Failed, PaymentStatus::Succeeded], [PaymentStatus::Expired, PaymentStatus::Succeeded],
]);

it('does not allow a pending payment to become refunded', function () {
    expect(fn () => requestedPayment()->apply(PaymentStatus::Refunded))->toThrow(InvalidPaymentTransitionException::class);
    expect(fn () => requestedPayment()->refund(100))->toThrow(InvalidPaymentTransitionException::class);
});

it('R40: accumulates confirmed refunds without exceeding the original amount', function () {
    $payment = requestedPayment();
    $payment->apply(PaymentStatus::Succeeded);
    $payment->refund(2500);
    expect($payment->status)->toBe(PaymentStatus::PartiallyRefunded)->and($payment->refundedAmount)->toBe(2500);
    $payment->refund(5000);
    expect($payment->status)->toBe(PaymentStatus::Refunded)->and($payment->refundedAmount)->toBe(7500);
    expect(fn () => $payment->refund(1))->toThrow(InvalidPaymentTransitionException::class);
});

it('refuses a zero or excessive refund', function (int $amount) {
    $payment = requestedPayment();
    $payment->apply(PaymentStatus::Succeeded);
    expect(fn () => $payment->refund($amount))->toThrow(InvalidPaymentTransitionException::class);
})->with([0, -1, 7501]);

it('preserves the checkout URL and capture identity when a status query omits navigation fields', function () {
    $payment = requestedPayment();
    $payment->attach(new ChargeResult('session', PaymentStatus::Pending, 'CRC', 7500, 'https://checkout.onvopay.com/pay/session', 'session', 'intent'));
    $payment->attach(new ChargeResult('session', PaymentStatus::Pending, 'CRC', 7500));
    expect($payment->checkoutUrl)->toBe('https://checkout.onvopay.com/pay/session')
        ->and($payment->checkoutReference)->toBe('session')->and($payment->captureReference)->toBe('intent');
});

it('keeps the exchange rate as a positive exact decimal', function () {
    expect(new ExchangeRate('500.125')->scaled)->toBe(500125000);
});

it('refuses an invalid exchange rate before creating a remote charge', function (string $rate) {
    expect(fn () => new ExchangeRate($rate))->toThrow(InvalidValueException::class);
})->with(['0', '-1', 'not-a-rate', '1e3', '0.0000001']);

it('knows the provider references of its charge, checkout and capture', function () {
    $payment = requestedPayment();
    expect($payment->hasReference('session'))->toBeFalse();

    $payment->attach(new ChargeResult('session', PaymentStatus::Pending, 'CRC', 7500, 'https://checkout.onvopay.com/pay/session', 'session', 'intent'));

    expect($payment->hasReference('session'))->toBeTrue()
        ->and($payment->hasReference('intent'))->toBeTrue()
        ->and($payment->hasReference('another-session'))->toBeFalse()
        ->and($payment->hasReference(''))->toBeFalse();
});

it('matches only the exact charged currency and amount', function (?string $currency, ?int $amount, bool $matches) {
    $payment = requestedPayment();
    $payment->attach(new ChargeResult('session', PaymentStatus::Pending, 'CRC', 7500));

    expect($payment->matchesCharge($currency, $amount))->toBe($matches);
})->with([
    'the charge' => ['CRC', 7500, true],
    'another amount' => ['CRC', 7499, false],
    'another currency' => ['USD', 7500, false],
    'no amount' => ['CRC', null, false],
    'no currency' => [null, 7500, false],
]);

it('matches nothing before the provider reports what it charged', function () {
    expect(requestedPayment()->matchesCharge('CRC', 0))->toBeFalse();
});

it('R29: records a balance received on delivery as a confirmed payment in colones with no online method', function () {
    $payment = paymentReceivedOnDelivery(5000);

    expect($payment->gateway)->toBe('manual')
        ->and($payment->purpose)->toBe(PaymentPurpose::Balance)
        ->and($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->method)->toBeNull()
        ->and($payment->chargedCurrency)->toBe('CRC')
        ->and($payment->chargedAmount)->toBe(5000)
        ->and($payment->refundedAmount)->toBe(0)
        ->and($payment->providerReference)->toBeNull()
        ->and($payment->manual)->toEqual(deliveryRecord());
});

it('never sends a payment received on delivery back through a gateway', function () {
    $payment = paymentReceivedOnDelivery();

    expect(fn () => $payment->attach(new ChargeResult('session', PaymentStatus::Succeeded, 'CRC', 7500)))->toThrow(InvalidPaymentTransitionException::class)
        ->and(fn () => $payment->apply(PaymentStatus::Failed))->toThrow(InvalidPaymentTransitionException::class)
        ->and($payment->hasReference('SINPE-2026-0001'))->toBeFalse();
});

it('keeps an online method only on gateway payments and a manual record only on payments received on delivery', function (string $gateway, ?PaymentMethod $method, bool $withRecord) {
    expect(fn () => Payment::reconstitute(new PaymentId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a61'), new BusinessId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62'), new Payable('order', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a63'), PaymentPurpose::Balance, new Colones(7500), $gateway, $method, null, PaymentStatus::Succeeded, null, null, null, null, 'CRC', 7500, 0, $withRecord ? deliveryRecord() : null))
        ->toThrow(InvalidValueException::class, 'payments.invalid_payment');
})->with([
    'manual with an online method' => ['manual', PaymentMethod::SinpeMobile, true],
    'manual without its record' => ['manual', null, false],
    'online without a method' => ['onvo', null, false],
    'online with a manual record' => ['onvo', PaymentMethod::Card, true],
]);

it('R29: requires a reference of at most 255 characters on a payment received on delivery', function (string $reference) {
    expect(fn () => deliveryRecord($reference))->toThrow(InvalidValueException::class, 'payments.invalid_manual_reference');
})->with(['empty' => [''], 'blank' => ['   '], 'too long' => [str_repeat('a', ManualRecord::MAX_REFERENCE_LENGTH + 1)]]);

it('keeps a reference of exactly 255 characters', function () {
    $reference = str_repeat('á', ManualRecord::MAX_REFERENCE_LENGTH);

    expect(deliveryRecord($reference)->reference)->toBe($reference);
});
