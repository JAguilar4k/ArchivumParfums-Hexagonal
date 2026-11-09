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

it('knows the provider references of its charge, checkout and capture', function () {
    $payment = requestedPayment();
    expect($payment->hasReference('session'))->toBeFalse();

    $payment->attach(new ChargeResult('session', PaymentStatus::Pending, 'CRC', 7500, 'https://checkout.onvopay.com/pay/session', 'session', 'intent'));

    expect($payment->hasReference('session'))->toBeTrue()
        ->and($payment->hasReference('intent'))->toBeTrue()
        ->and($payment->hasReference('another-session'))->toBeFalse()
        ->and($payment->hasReference(''))->toBeFalse();
});

it('matches only the exact charged currency and amount, which only a failure may leave out', function (?string $currency, ?int $amount, bool $failure, bool $matches) {
    $payment = requestedPayment();
    $payment->attach(new ChargeResult('session', PaymentStatus::Pending, 'CRC', 7500));

    expect($payment->matchesCharge($currency, $amount, $failure))->toBe($matches);
})->with([
    'the charge' => ['CRC', 7500, false, true],
    'another amount' => ['CRC', 7499, false, false],
    'another currency' => ['USD', 7500, false, false],
    'no amount' => ['CRC', null, false, false],
    'no currency' => [null, 7500, false, false],
    'a failure without amount' => ['CRC', null, true, true],
    'a failure with the charge' => ['CRC', 7500, true, true],
    'a failure with another amount' => ['CRC', 7499, true, false],
    'a failure in another currency' => ['USD', null, true, false],
    'a failure without currency' => [null, null, true, false],
]);

it('matches nothing before the provider reports what it charged', function () {
    expect(requestedPayment()->matchesCharge('CRC', 0))->toBeFalse()
        ->and(requestedPayment()->matchesCharge('CRC', null, true))->toBeFalse();
});

it('R40: registers a pending refund tied to the confirmed payment, counting what it already returned and the pending refunds', function () {
    $payment = requestedPayment();
    $payment->apply(PaymentStatus::Succeeded);
    $payment->refund(2000);

    $refund = $payment->requestRefund('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', 2500, 3000, '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a65');

    expect($refund->paymentId)->toBe($payment->id->value)->and($refund->businessId)->toBe($payment->businessId->value)
        ->and($refund->amount)->toBe(2500)->and($refund->orderLineId)->toBe('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a65')
        ->and($refund->status)->toBe('pending')->and($refund->attempted)->toBeFalse()->and($refund->chargedAmount)->toBeNull();
    // Registering a refund returns nothing yet: only its confirmation does.
    expect($payment->refundedAmount)->toBe(2000)->and($payment->status)->toBe(PaymentStatus::PartiallyRefunded);
});

it('R40: refuses a refund above what the payment paid and has not returned or promised yet', function (int $amount, int $pending) {
    $payment = requestedPayment();
    $payment->apply(PaymentStatus::Succeeded);
    $payment->refund(2000);

    expect(fn () => $payment->requestRefund('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', $amount, $pending))
        ->toThrow(InvalidPaymentTransitionException::class, 'payments.invalid_refund');
})->with([
    'more than what is left' => [5501, 0],
    'more than what the pending refunds leave' => [2501, 3000],
    'nothing' => [0, 0],
    'a negative amount' => [-1, 0],
    'a negative pending amount' => [100, -1],
]);

it('R40: refuses a refund of a payment with no confirmed money left', function (PaymentStatus $status) {
    $payment = requestedPayment();
    if ($status !== PaymentStatus::Pending) {
        $payment->apply($status);
    }

    expect(fn () => $payment->requestRefund('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', 100, 0))
        ->toThrow(InvalidPaymentTransitionException::class, 'payments.not_refundable');
})->with([PaymentStatus::Pending, PaymentStatus::Failed, PaymentStatus::Expired]);

it('R40: refuses another refund once the payment returned everything', function () {
    $payment = requestedPayment();
    $payment->apply(PaymentStatus::Succeeded);
    $payment->refund(7500);

    expect(fn () => $payment->requestRefund('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', 1, 0))
        ->toThrow(InvalidPaymentTransitionException::class, 'payments.not_refundable');
});
