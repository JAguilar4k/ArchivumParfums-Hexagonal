<?php

declare(strict_types=1);

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeReference;
use Src\Payments\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Payments\Payments\Domain\ValueObjects\RefundRequest;
use Src\Payments\Payments\Infrastructure\Gateways\PayPal\PayPalGateway;
use Src\Shared\Domain\Exceptions\InvalidValueException;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('R37: creates a USD order with a stable idempotency key', function (int $colones, string $rate, int $cents, string $decimal): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'order-1', 'status' => 'CREATED', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=order-1']]], 201),
    ]);

    $result = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->create(new ChargeRequest('payment-1', 'business-1', $colones, exchangeRate: $rate));

    expect($result->status)->toBe(PaymentStatus::Pending);
    expect($result->chargedAmount)->toBe($cents);
    expect($result->currency)->toBe('USD');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
        && $request['purchase_units'][0]['amount'] === ['currency_code' => 'USD', 'value' => $decimal]
        && $request['purchase_units'][0]['custom_id'] === 'business-1:payment-1'
        && preg_match('/^[a-f0-9]{32}$/D', $request->header('PayPal-Request-Id')[0] ?? '') === 1);
    Http::assertSentCount(2);
})->with([[15_000, '500.000000', 3_000, '30.00'], [10_000, '640', 1_563, '15.63'], [1, '200', 1, '0.01']]);

it('reports an explicit PayPal instrument decline as failed', function (): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['details' => [['issue' => 'INSTRUMENT_DECLINED']]], 422),
    ]);

    $result = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->create(new ChargeRequest('payment-1', 'business-1', 15_000, exchangeRate: '500'));

    expect($result->status)->toBe(PaymentStatus::Failed);
    Http::assertSentCount(2);
});

it('reuses a creation key while keeping the capture operation independent', function (): void {
    $keys = [];
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => function (Request $request) use (&$keys): PromiseInterface {
            $keys[] = $request->header('PayPal-Request-Id')[0];

            return Http::response(['id' => 'order-1', 'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=order-1']]], 201);
        },
        'api-m.sandbox.paypal.com/v2/checkout/orders/order-1' => Http::response(['id' => 'order-1', 'status' => 'APPROVED', 'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '30.00']]]]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/order-1/capture' => function (Request $request) use (&$keys): PromiseInterface {
            $keys[] = $request->header('PayPal-Request-Id')[0];

            return Http::response(['id' => 'order-1', 'purchase_units' => [['payments' => ['captures' => [['id' => 'capture-1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '30.00']]]]]]], 201);
        },
    ]);
    $gateway = new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c');
    $request = new ChargeRequest('payment-1', 'business-1', 15_000, exchangeRate: '500');

    $gateway->create($request);
    $gateway->create($request);
    $gateway->status(new ChargeReference('payment-1', 'order-1', 'USD', 3_000));

    expect($keys)->toHaveCount(3);
    expect($keys[0])->toBe($keys[1]);
    expect($keys[2])->not->toBe($keys[0]);
    Http::assertSentCount(8);
});

it('reports a network error without losing the distinction from a decline', function (): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::failedConnection(),
    ]);

    expect(fn () => (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->create(new ChargeRequest('payment-1', 'business-1', 15_000, exchangeRate: '500')))->toThrow(GatewayUnavailableException::class);

    Http::assertSentCount(2);
});

it('does not contact PayPal with an unsupported method or exchange rate', function (?string $rate, PaymentMethod $method): void {
    expect(fn () => (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->create(new ChargeRequest('payment-1', 'business-1', 15_000, $method, $rate)))->toThrow(InvalidValueException::class);

    Http::assertNothingSent();
})->with([[null, PaymentMethod::Card], ['0', PaymentMethod::Card], ['-1', PaymentMethod::Card], ['1.1234567', PaymentMethod::Card], ['500', PaymentMethod::SinpeMobile]]);

it('credits an approved order only after capture completes', function (string $captureStatus, PaymentStatus $expected): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/order-1' => Http::response(['id' => 'order-1', 'status' => 'APPROVED', 'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '30.00']]]]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/order-1/capture' => Http::response(['id' => 'order-1', 'purchase_units' => [['payments' => ['captures' => [['id' => 'capture-1', 'status' => $captureStatus, 'amount' => ['currency_code' => 'USD', 'value' => '30.00']]]]]]], 201),
    ]);

    $result = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'order-1', 'USD', 3_000));

    expect($result->status)->toBe($expected);
    expect($result->captureReference)->toBe('capture-1');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/order-1/capture') && $request->body() === '{}' && $request->hasHeader('PayPal-Request-Id'));
    Http::assertSentCount(4);
})->with([['COMPLETED', PaymentStatus::Succeeded], ['DECLINED', PaymentStatus::Failed], ['PENDING', PaymentStatus::Pending]]);

it('does not capture again when an order already contains its completed capture', function (): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/order-1' => Http::response(['id' => 'order-1', 'status' => 'COMPLETED', 'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '30.00'], 'payments' => ['captures' => [['id' => 'capture-1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '30.00']]]]]]]),
    ]);

    $gateway = new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c');
    $reference = new ChargeReference('payment-1', 'order-1', 'USD', 3_000);
    $first = $gateway->status($reference);
    $second = $gateway->status($reference);

    expect($first->status)->toBe(PaymentStatus::Succeeded);
    expect($second->captureReference)->toBe('capture-1');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/capture'));
    Http::assertSentCount(4);
});

it('keeps a created order pending without issuing a capture', function (): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/order-1' => Http::response(['id' => 'order-1', 'status' => 'CREATED', 'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '30.00']]]]),
    ]);

    $result = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'order-1', 'USD', 3_000));

    expect($result->status)->toBe(PaymentStatus::Pending);
    Http::assertSentCount(2);
});

it('rejects a mismatched order amount before capture', function (): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/order-1' => Http::response(['id' => 'order-1', 'status' => 'APPROVED', 'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '3.00']]]]),
    ]);

    expect(fn () => (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'order-1', 'USD', 3_000)))->toThrow(InvalidValueException::class);

    Http::assertSentCount(2);
});

it('rejects a completed capture with an unexpected amount', function (): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/order-1' => Http::response(['id' => 'order-1', 'status' => 'COMPLETED', 'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '30.00'], 'payments' => ['captures' => [['id' => 'capture-1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'CRC', 'value' => '30.00']]]]]]]),
    ]);

    expect(fn () => (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'order-1', 'USD', 3_000)))->toThrow(InvalidValueException::class);

    Http::assertSentCount(2);
});

it('distinguishes an explicit capture refusal from a network timeout', function (): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/order-1' => Http::response(['id' => 'order-1', 'status' => 'APPROVED', 'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '30.00']]]]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/order-1/capture' => Http::response(['details' => [['issue' => 'INSTRUMENT_DECLINED']]], 422),
    ]);

    $result = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->status(new ChargeReference('payment-1', 'order-1', 'USD', 3_000));

    expect($result->status)->toBe(PaymentStatus::Failed);
    Http::assertSentCount(4);
});

it('refuses production endpoints', function (): void {
    expect(fn () => new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c', baseUrl: 'https://api-m.paypal.com'))->toThrow(InvalidValueException::class);

    Http::assertNothingSent();
});

it('R37: converts the running total of the refunds, so together they return exactly the captured cents', function (): void {
    $gateway = new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c');

    // ₡15.000 at 503.17 were captured as $29.81; two refunds of ₡7.500 each would round to $14.91 twice, one cent too many.
    $first = $gateway->refundAmount(7_500, 0, '503.17');
    $second = $gateway->refundAmount(7_500, 7_500, '503.17');

    expect($first)->toBe(1_491)
        ->and($second)->toBe(1_490)
        ->and($first + $second)->toBe($gateway->refundAmount(15_000, 0, '503.17'))
        ->and($gateway->refundAmount(15_000, 0, '503.17'))->toBe(2_981);
    Http::assertNothingSent();
});

it('refunds the capture with its cents under an idempotency key of the refund and the custom_id Archivum reads back', function (string $status, string $expected): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/payments/captures/capture-1/refund' => Http::response(['id' => 'paypal-refund-1', 'status' => $status, 'amount' => ['currency_code' => 'USD', 'value' => '14.90']], 201),
    ]);

    $result = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))
        ->refund(new RefundRequest('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62', 'order-1', 1_490, 'capture-1'));

    expect($result->providerReference)->toBe('paypal-refund-1')->and($result->status)->toBe($expected);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v2/payments/captures/capture-1/refund')
        && $request['amount'] === ['currency_code' => 'USD', 'value' => '14.90']
        && $request['custom_id'] === '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62:0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64'
        && $request->header('PayPal-Request-Id') === [substr(hash('sha256', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64:refund'), 0, 32)]
        && $request->header('Prefer') === ['return=representation']);
    Http::assertSentCount(2);
})->with([['COMPLETED', 'succeeded'], ['PENDING', 'pending'], ['FAILED', 'failed'], ['CANCELLED', 'failed']]);

it('reports a refusal of PayPal as a failed refund, not as an unavailable provider', function (): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/payments/captures/capture-1/refund' => Http::response(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'REFUND_AMOUNT_EXCEEDED']]], 422),
    ]);

    $result = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))
        ->refund(new RefundRequest('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62', 'order-1', 1_491, 'capture-1'));

    // PayPal created no refund, so there is no reference of the provider to keep.
    expect($result->status)->toBe('failed')->and($result->providerReference)->toBeNull();
});

it('asks only for the state of a refund PayPal already accepted', function (): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/payments/refunds/paypal-refund-1' => Http::response(['id' => 'paypal-refund-1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '14.90']]),
    ]);

    $result = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))
        ->refund(new RefundRequest('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62', 'order-1', 1_490, 'capture-1', 'paypal-refund-1'));

    expect($result->status)->toBe('succeeded');
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/refund'));
    Http::assertSentCount(2);
});

it('rejects a refund answer with other cents, another currency or another refund', function (array $answer, string $exception): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/payments/refunds/paypal-refund-1' => Http::response($answer),
    ]);

    expect(fn () => (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))
        ->refund(new RefundRequest('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62', 'order-1', 1_490, 'capture-1', 'paypal-refund-1')))
        ->toThrow($exception);
})->with([
    'other cents' => [['id' => 'paypal-refund-1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '14.91']], InvalidValueException::class],
    'another currency' => [['id' => 'paypal-refund-1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'EUR', 'value' => '14.90']], InvalidValueException::class],
    'another refund' => [['id' => 'paypal-refund-2', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '14.90']], GatewayUnavailableException::class],
]);

it('reports a refund that cannot reach PayPal as unavailable, and one without capture without contacting PayPal', function (): void {
    Http::fake(['api-m.sandbox.paypal.com/*' => Http::failedConnection()]);
    $gateway = new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c');

    expect(fn () => $gateway->refund(new RefundRequest('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62', 'order-1', 1_490, 'capture-1')))
        ->toThrow(GatewayUnavailableException::class);
    Http::assertSentCount(1);

    expect(fn () => $gateway->refund(new RefundRequest('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64', '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62', 'order-1', 1_490)))
        ->toThrow(InvalidValueException::class, 'payments.invalid_refund');
    Http::assertSentCount(1);
});

it('verifies webhook origin with PayPal and preserves the original event JSON', function (): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
    ]);
    $body = '{"id":"event-1", "amount":1.00}';
    $headers = ['PayPal-Auth-Algo' => 'SHA256withRSA', 'PayPal-Cert-Url' => 'https://api.sandbox.paypal.com/cert', 'PayPal-Transmission-Id' => 'transmission-1', 'PayPal-Transmission-Sig' => 'fake-signature', 'PayPal-Transmission-Time' => '2026-10-24T12:00:00Z'];

    $verified = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c', 'webhook-1'))->verifyWebhook($body, $headers);

    expect($verified)->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/verify-webhook-signature') && str_contains($request->body(), '"webhook_event":'.$body) && $request['webhook_id'] === 'webhook-1');
    Http::assertSentCount(2);
});

it('rejects incomplete webhook headers without contacting PayPal', function (): void {
    $gateway = new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c', 'webhook-1');

    expect($gateway->verifyWebhook('{}', []))->toBeFalse();

    Http::assertNothingSent();
});

it('does not treat an OAuth or upstream failure as a payment decline', function (int $status): void {
    Http::fake(['api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['error' => 'unavailable'], $status)]);

    expect(fn () => (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->create(new ChargeRequest('payment-1', 'business-1', 15_000, exchangeRate: '500')))->toThrow(GatewayUnavailableException::class);

    Http::assertSentCount(1);
})->with([401, 503]);

it('reads an approved order notice from its purchase unit, in cents', function (): void {
    $event = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->parseWebhook((string) json_encode([
        'id' => 'WH-1', 'event_type' => 'CHECKOUT.ORDER.APPROVED',
        'resource' => ['id' => 'order-1', 'status' => 'APPROVED', 'purchase_units' => [[
            'custom_id' => '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62:0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a61', 'amount' => ['currency_code' => 'USD', 'value' => '15.63'],
        ]]],
    ]));

    expect($event->gateway)->toBe('paypal')
        ->and($event->eventId)->toBe('WH-1')
        ->and($event->reference)->toBe('order-1')
        ->and($event->businessId?->value)->toBe('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62')
        ->and($event->paymentId?->value)->toBe('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a61')
        ->and($event->currency)->toBe('USD')
        ->and($event->amount)->toBe(1_563);
    Http::assertNothingSent();
});

it('reads a capture notice by the order it belongs to', function (): void {
    $event = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->parseWebhook((string) json_encode([
        'id' => 'WH-2', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => ['id' => 'capture-1', 'status' => 'COMPLETED', 'custom_id' => 'not-ours', 'amount' => ['currency_code' => 'USD', 'value' => '30.00'],
            'supplementary_data' => ['related_ids' => ['order_id' => 'order-1']]],
    ]));

    expect($event->reference)->toBe('order-1')
        ->and($event->businessId)->toBeNull()
        ->and($event->paymentId)->toBeNull()
        ->and($event->amount)->toBe(3_000);
});

it('reads a refund notice by its refund, the capture it returns and the custom_id Archivum sent, in cents', function (): void {
    $event = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->parseWebhook((string) json_encode([
        'id' => 'WH-3', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource_type' => 'refund',
        'resource' => ['id' => 'paypal-refund-1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '14.90'],
            'custom_id' => '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62:0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64',
            'links' => [
                ['href' => 'https://api.sandbox.paypal.com/v2/payments/refunds/paypal-refund-1', 'rel' => 'self', 'method' => 'GET'],
                ['href' => 'https://api.sandbox.paypal.com/v2/payments/captures/capture-1', 'rel' => 'up', 'method' => 'GET'],
            ]],
    ]));

    expect($event->aboutRefund())->toBeTrue()
        ->and($event->aboutCharge())->toBeFalse()
        ->and($event->refundReference)->toBe('paypal-refund-1')
        ->and($event->reference)->toBe('capture-1')
        ->and($event->businessId?->value)->toBe('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a62')
        ->and($event->refundId?->value)->toBe('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a64')
        ->and($event->currency)->toBe('USD')
        ->and($event->amount)->toBe(1_490);
    Http::assertNothingSent();
});

it('reads a refund notice without the identifiers Archivum sends only by the provider\'s references', function (): void {
    $event = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->parseWebhook((string) json_encode([
        'id' => 'WH-4', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
        'resource' => ['id' => 'paypal-refund-1', 'custom_id' => 'not-ours', 'amount' => ['currency_code' => 'USD', 'value' => '14.90']],
    ]));

    expect($event->aboutRefund())->toBeTrue()
        ->and($event->reference)->toBeNull()
        ->and($event->businessId)->toBeNull()
        ->and($event->refundId)->toBeNull();
});

it('reads other notices without a charge or a refund', function (string $type): void {
    $event = (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->parseWebhook((string) json_encode([
        'id' => 'WH-5', 'event_type' => $type, 'resource' => ['id' => 'resource-1', 'amount' => ['currency_code' => 'USD', 'value' => '30.00']],
    ]));

    expect($event->aboutCharge())->toBeFalse()->and($event->aboutRefund())->toBeFalse()->and($event->eventId)->toBe('WH-5');
})->with(['PAYMENT.CAPTURE.REVERSED', 'CUSTOMER.DISPUTE.CREATED']);

it('refuses a body that is not a notice of PayPal', function (string $body): void {
    expect(fn () => (new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c'))->parseWebhook($body))
        ->toThrow(InvalidValueException::class, 'payments.invalid_webhook');
})->with([
    'not JSON' => ['not-json'],
    'without id' => ['{"event_type":"CHECKOUT.ORDER.APPROVED","resource":{"id":"order-1"}}'],
    'without resource' => ['{"id":"WH-1","event_type":"CHECKOUT.ORDER.APPROVED"}'],
    'an approval without order' => ['{"id":"WH-1","event_type":"CHECKOUT.ORDER.APPROVED","resource":{"purchase_units":[]}}'],
    'a capture without order' => ['{"id":"WH-1","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"capture-1"}}'],
    'a refund without its id' => ['{"id":"WH-1","event_type":"PAYMENT.CAPTURE.REFUNDED","resource":{"status":"COMPLETED"}}'],
]);
