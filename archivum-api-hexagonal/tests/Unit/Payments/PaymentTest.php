<?php

declare(strict_types=1);

use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeResult;
use Src\Payments\Payments\Domain\ValueObjects\ExchangeRate;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

function requestedPayment(int $amount = 7500, string $gateway = 'onvo'): Payment
{
    return Payment::create(new PaymentId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a61'), new BusinessId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62'), new Payable('order', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a63'), PaymentPurpose::Deposit, new Colones($amount), $gateway, PaymentMethod::Card);
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
