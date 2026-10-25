<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeReference;
use Src\Payments\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Payments\Payments\Domain\ValueObjects\RefundRequest;
use Src\Payments\Payments\Infrastructure\Gateways\Onvo\OnvoGateway;
use Src\Shared\Domain\Exceptions\InvalidValueException;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('creates a hosted checkout in CRC cents with the requested supported method', function (PaymentMethod $method, string $providerMethod): void {
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response([
        'id' => 'session-1', 'url' => 'https://checkout.onvopay.com/pay/session-1', 'mode' => 'test', 'paymentIntentId' => 'intent-1',
    ], 201)]);

    $result = (new OnvoGateway('onvo_test_fake', 'https://example.test/return', 'https://example.test/cancel'))->create(new ChargeRequest('payment-1', 'business-1', 15_000, $method));

    expect($result->status)->toBe(PaymentStatus::Pending);
    expect($result->providerReference)->toBe('session-1');
    expect($result->captureReference)->toBe('intent-1');
    expect($result->chargedAmount)->toBe(15_000);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request['lineItems'] === [['quantity' => 1, 'unitAmount' => 1_500_000, 'currency' => 'CRC', 'description' => 'payment-1']]
        && $request['paymentMethodTypes'] === [$providerMethod]
        && $request['metadata'] === ['paymentId' => 'payment-1', 'businessId' => 'business-1']
        && $request['redirectUrl'] === 'https://example.test/return'
        && $request->hasHeader('Authorization', 'Bearer onvo_test_fake'));
})->with([[PaymentMethod::Card, 'card'], [PaymentMethod::SinpeMobile, 'mobile_number']]);

it('reports an explicit declined checkout as failed', function (): void {
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['code' => 'declined'], 402)]);

    $result = (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->create(new ChargeRequest('payment-1', 'business-1', 5_000));

    expect($result->status)->toBe(PaymentStatus::Failed);
    Http::assertSentCount(1);
});

it('reports communication errors without retrying an uncertain checkout creation', function (): void {
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::failedConnection()]);

    expect(fn () => (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->create(new ChargeRequest('payment-1', 'business-1', 5_000)))->toThrow(GatewayUnavailableException::class);

    Http::assertSentCount(1);
});

it('reports an upstream checkout failure as unavailable instead of a decline', function (): void {
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['error' => 'unavailable'], 503)]);

    expect(fn () => (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->create(new ChargeRequest('payment-1', 'business-1', 5_000)))->toThrow(GatewayUnavailableException::class);

    Http::assertSentCount(1);
});

it('requires the authoritative intent outcome even when checkout reports paid', function (string $status, int $attempts, PaymentStatus $expected): void {
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/session-1' => Http::response(['id' => 'session-1', 'mode' => 'test', 'status' => 'expired', 'paymentStatus' => 'paid', 'paymentIntentId' => 'intent-1']),
        'api.onvopay.com/v1/payment-intents/intent-1' => Http::response(['id' => 'intent-1', 'mode' => 'test', 'amount' => 500_000.0, 'currency' => 'CRC', 'status' => $status, 'confirmationAttempts' => $attempts]),
    ]);

    $result = (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'session-1', 'CRC', 5_000));

    expect($result->status)->toBe($expected);
    expect($result->providerReference)->toBe('session-1');
    Http::assertSentCount(2);
})->with([
    ['succeeded', 1, PaymentStatus::Succeeded],
    ['requires_payment_method', 0, PaymentStatus::Pending],
    ['processing', 1, PaymentStatus::Pending],
    ['canceled', 1, PaymentStatus::Expired],
]);

it('closes the declined checkout and intent before reporting a terminal failure', function (): void {
    Http::fakeSequence()
        ->push(['id' => 'session-1', 'mode' => 'test', 'status' => 'open', 'paymentIntentId' => 'intent-1'])
        ->push(['id' => 'intent-1', 'mode' => 'test', 'amount' => 500_000, 'currency' => 'CRC', 'status' => 'requires_payment_method', 'confirmationAttempts' => 1])
        ->push([], 201)
        ->push([], 201)
        ->push(['id' => 'session-1', 'mode' => 'test', 'status' => 'expired', 'paymentIntentId' => 'intent-1'])
        ->push(['id' => 'intent-1', 'mode' => 'test', 'amount' => 500_000, 'currency' => 'CRC', 'status' => 'canceled']);

    $result = (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'session-1', 'CRC', 5_000));

    expect($result->status)->toBe(PaymentStatus::Failed);
    expect($result->captureReference)->toBe('intent-1');
    Http::assertSentInOrder(array_map(fn (string $expected): Closure => fn (Request $request): bool => $request->method().' '.$request->url() === $expected, [
        'GET https://api.onvopay.com/v1/checkout/sessions/session-1',
        'GET https://api.onvopay.com/v1/payment-intents/intent-1',
        'POST https://api.onvopay.com/v1/checkout/sessions/session-1/expire',
        'POST https://api.onvopay.com/v1/payment-intents/intent-1/cancel',
        'GET https://api.onvopay.com/v1/checkout/sessions/session-1',
        'GET https://api.onvopay.com/v1/payment-intents/intent-1',
    ]));
});

it('keeps an uncertain remote closure pending even after successful cancellation responses', function (string $initialStatus, string $sessionStatus, string $intentStatus): void {
    Http::fakeSequence()
        ->push(['id' => 'session-1', 'mode' => 'test', 'status' => 'open', 'paymentIntentId' => 'intent-1'])
        ->push(['id' => 'intent-1', 'mode' => 'test', 'amount' => 500_000, 'currency' => 'CRC', 'status' => $initialStatus, 'confirmationAttempts' => 1])
        ->push([], 201)
        ->push([], 201)
        ->push(['id' => 'session-1', 'mode' => 'test', 'status' => $sessionStatus, 'paymentIntentId' => 'intent-1'])
        ->push(['id' => 'intent-1', 'mode' => 'test', 'amount' => 500_000, 'currency' => 'CRC', 'status' => $intentStatus]);

    expect(fn () => (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'session-1', 'CRC', 5_000)))->toThrow(GatewayUnavailableException::class);

    Http::assertSentCount(6);
})->with([
    ['requires_payment_method', 'open', 'canceled'],
    ['requires_payment_method', 'expired', 'requires_payment_method'],
    ['requires_payment_method', 'expired', 'processing'],
    ['canceled', 'open', 'canceled'],
]);

it('accepts a successful charge that wins the race against closing a declined checkout', function (): void {
    Http::fakeSequence()
        ->push(['id' => 'session-1', 'mode' => 'test', 'status' => 'open', 'paymentIntentId' => 'intent-1'])
        ->push(['id' => 'intent-1', 'mode' => 'test', 'amount' => 500_000, 'currency' => 'CRC', 'status' => 'requires_payment_method', 'lastPaymentError' => ['code' => 'declined']])
        ->push([], 400)
        ->push([], 400)
        ->push(['id' => 'session-1', 'mode' => 'test', 'status' => 'complete', 'paymentIntentId' => 'intent-1'])
        ->push(['id' => 'intent-1', 'mode' => 'test', 'amount' => 500_000, 'currency' => 'CRC', 'status' => 'succeeded']);

    $result = (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'session-1', 'CRC', 5_000));

    expect($result->status)->toBe(PaymentStatus::Succeeded);
    expect($result->captureReference)->toBe('intent-1');
    Http::assertSentCount(6);
});

it('verifies closed remote state after losing both cancellation responses', function (): void {
    Http::fakeSequence()
        ->push(['id' => 'session-1', 'mode' => 'test', 'status' => 'open', 'paymentIntentId' => 'intent-1'])
        ->push(['id' => 'intent-1', 'mode' => 'test', 'amount' => 500_000, 'currency' => 'CRC', 'status' => 'requires_payment_method', 'confirmationAttempts' => 1])
        ->pushResponse(Http::failedConnection())
        ->pushResponse(Http::failedConnection())
        ->push(['id' => 'session-1', 'mode' => 'test', 'status' => 'expired', 'paymentIntentId' => 'intent-1'])
        ->push(['id' => 'intent-1', 'mode' => 'test', 'amount' => 500_000, 'currency' => 'CRC', 'status' => 'canceled']);

    $result = (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'session-1', 'CRC', 5_000));

    expect($result->status)->toBe(PaymentStatus::Failed);
    Http::assertSentCount(6);
});

it('does not treat a changed session intent or a mismatched final amount as safely closed', function (string $sessionIntent, int $amount, string $exception): void {
    Http::fakeSequence()
        ->push(['id' => 'session-1', 'mode' => 'test', 'status' => 'open', 'paymentIntentId' => 'intent-1'])
        ->push(['id' => 'intent-1', 'mode' => 'test', 'amount' => 500_000, 'currency' => 'CRC', 'status' => 'requires_payment_method', 'confirmationAttempts' => 1])
        ->push([], 201)
        ->push([], 201)
        ->push(['id' => 'session-1', 'mode' => 'test', 'status' => 'expired', 'paymentIntentId' => $sessionIntent])
        ->push(['id' => 'intent-1', 'mode' => 'test', 'amount' => $amount, 'currency' => 'CRC', 'status' => 'canceled']);

    expect(fn () => (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'session-1', 'CRC', 5_000)))->toThrow($exception);
})->with([['intent-other', 500_000, GatewayUnavailableException::class], ['intent-1', 499_999, InvalidValueException::class]]);

it('keeps an unknown intent status pending and logs only local context', function (): void {
    Log::shouldReceive('warning')->once()->with('Unknown payment status received from gateway.', ['gateway' => 'onvo', 'payment_id' => 'payment-1']);
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/session-1' => Http::response(['id' => 'session-1', 'mode' => 'test', 'paymentIntentId' => 'intent-1']),
        'api.onvopay.com/v1/payment-intents/intent-1' => Http::response(['id' => 'intent-1', 'mode' => 'test', 'amount' => 500_000, 'currency' => 'CRC', 'status' => 'future-status']),
    ]);

    $result = (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'session-1', 'CRC', 5_000));

    expect($result->status)->toBe(PaymentStatus::Pending);
    Http::assertSentCount(2);
});

it('rejects an authoritative intent with an unexpected amount or currency', function (mixed $amount, string $currency): void {
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/session-1' => Http::response(['id' => 'session-1', 'mode' => 'test', 'paymentIntentId' => 'intent-1']),
        'api.onvopay.com/v1/payment-intents/intent-1' => Http::response(['id' => 'intent-1', 'mode' => 'test', 'amount' => $amount, 'currency' => $currency, 'status' => 'succeeded']),
    ]);

    expect(fn () => (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'session-1', 'CRC', 5_000)))->toThrow(InvalidValueException::class);

    Http::assertSentCount(2);
})->with([[50_000, 'CRC'], [500_000.01, 'CRC'], ['500000', 'CRC'], [500_000, 'USD']]);

it('does not accept a live key or a live checkout response', function (): void {
    expect(fn () => new OnvoGateway('invalid-key', 'https://example.test/r', 'https://example.test/c'))->toThrow(InvalidValueException::class);
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['id' => 'session-1', 'url' => 'https://checkout.onvopay.com/pay/session-1', 'mode' => 'live'], 201)]);

    expect(fn () => (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->create(new ChargeRequest('payment-1', 'business-1', 5_000)))->toThrow(GatewayUnavailableException::class);

    Http::assertSentCount(1);
});

it('expires only a session with no intention and an authoritative expired state', function (string $status, PaymentStatus $expected): void {
    Http::fake(['api.onvopay.com/v1/checkout/sessions/session-1' => Http::response(['id' => 'session-1', 'mode' => 'test', 'status' => $status, 'paymentStatus' => 'paid'])]);

    $result = (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'session-1', 'CRC', 5_000));

    expect($result->status)->toBe($expected);
    Http::assertSentCount(1);
})->with([['expired', PaymentStatus::Expired], ['complete', PaymentStatus::Pending]]);

it('refuses a checkout URL outside the provider domain', function (): void {
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['id' => 'session-1', 'mode' => 'test', 'url' => 'https://onvopay.com.evil.test/pay'], 201)]);

    expect(fn () => (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->create(new ChargeRequest('payment-1', 'business-1', 5_000)))->toThrow(GatewayUnavailableException::class);

    Http::assertSentCount(1);
});

it('requests and queries a refund in cents against its original intent', function (): void {
    Http::fake([
        'api.onvopay.com/v1/refunds' => Http::response(['id' => 'refund-1'], 201),
        'api.onvopay.com/v1/refunds/refund-1' => Http::response(['id' => 'refund-1', 'mode' => 'test', 'paymentIntentId' => 'intent-1', 'amount' => 250_000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);

    $result = (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->refund(new RefundRequest('local-refund', 'session-1', 2_500, 'intent-1'));

    expect($result->status)->toBe('succeeded');
    expect($result->providerReference)->toBe('refund-1');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['paymentIntentId'] === 'intent-1' && $request['amount'] === 250_000);
    Http::assertSentCount(2);
});

it('queries an existing pending refund without sending a second refund request', function (): void {
    Http::fake(['api.onvopay.com/v1/refunds/refund-1' => Http::response(['id' => 'refund-1', 'mode' => 'test', 'paymentIntentId' => 'intent-1', 'amount' => 250_000, 'currency' => 'CRC', 'status' => 'succeeded'])]);

    $result = (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->refund(new RefundRequest('local-refund', 'session-1', 2_500, 'intent-1', providerRefundReference: 'refund-1'));

    expect($result->status)->toBe('succeeded');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET');
    Http::assertSentCount(1);
});

it('preserves the refund reference when its subsequent query cannot communicate', function (): void {
    Http::fake([
        'api.onvopay.com/v1/refunds' => Http::response(['id' => 'refund-1'], 201),
        'api.onvopay.com/v1/refunds/refund-1' => Http::failedConnection(),
    ]);

    $result = (new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c'))->refund(new RefundRequest('local-refund', 'session-1', 2_500, 'intent-1'));

    expect($result->status)->toBe('pending');
    expect($result->providerReference)->toBe('refund-1');
    Http::assertSentCount(2);
});

it('accepts only the configured webhook secret regardless of header casing', function (): void {
    $gateway = new OnvoGateway('onvo_test_fake', 'https://example.test/r', 'https://example.test/c', 'webhook_secret_fake');

    expect($gateway->verifyWebhook('{}', ['X-Webhook-Secret' => 'webhook_secret_fake']))->toBeTrue();
    expect($gateway->verifyWebhook('{}', ['x-webhook-secret' => 'wrong']))->toBeFalse();
    expect($gateway->verifyWebhook('{}', []))->toBeFalse();
    Http::assertNothingSent();
});
