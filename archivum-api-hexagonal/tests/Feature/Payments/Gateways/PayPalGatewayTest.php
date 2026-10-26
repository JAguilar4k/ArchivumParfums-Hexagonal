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

it('refuses production endpoints and keeps unsupported refunds offline', function (): void {
    expect(fn () => new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c', baseUrl: 'https://api-m.paypal.com'))->toThrow(InvalidValueException::class);
    $gateway = new PayPalGateway('fake-client', 'fake-secret', 'https://example.test/r', 'https://example.test/c');

    expect(fn () => $gateway->refund(new RefundRequest('refund-1', 'order-1', 5_000)))->toThrow(InvalidValueException::class, 'payments.refund_not_supported');

    Http::assertNothingSent();
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
