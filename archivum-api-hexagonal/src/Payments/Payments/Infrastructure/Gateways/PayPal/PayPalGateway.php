<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Gateways\PayPal;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Payments\Payments\Domain\Contracts\PaymentGateway;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeReference;
use Src\Payments\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Payments\Payments\Domain\ValueObjects\ChargeResult;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Payments\Payments\Domain\ValueObjects\RefundRequest;
use Src\Payments\Payments\Domain\ValueObjects\RefundResult;
use Src\Payments\Payments\Infrastructure\Gateways\GatewayMoney;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class PayPalGateway implements PaymentGateway
{
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $returnUrl,
        private string $cancelUrl,
        private string $webhookId = '',
        private string $baseUrl = 'https://api-m.sandbox.paypal.com',
        private int $timeout = 10,
        private int $connectTimeout = 3,
    ) {
        if ($clientId === '' || $clientSecret === '' || ! in_array($baseUrl, ['https://api-m.sandbox.paypal.com', 'https://api.sandbox.paypal.com'], true) || $timeout <= 0 || $connectTimeout <= 0) {
            throw new InvalidValueException('payments.invalid_configuration');
        }
    }

    public function supportedMethods(): array
    {
        return [PaymentMethod::Card];
    }

    public function validateCharge(ChargeRequest $request): void
    {
        if ($request->method !== PaymentMethod::Card) {
            throw new InvalidValueException('payments.unsupported_method');
        }

        GatewayMoney::dollarsFromColones($request->amount, $request->exchangeRate);
    }

    public function create(ChargeRequest $request): ChargeResult
    {
        $this->validateCharge($request);

        $amount = GatewayMoney::dollarsFromColones($request->amount, $request->exchangeRate);
        $response = $this->send(fn (PendingRequest $http): Response => $http->withHeaders(['PayPal-Request-Id' => $this->idempotencyKey($request->paymentId, 'create')])->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $request->paymentId,
                'custom_id' => $request->businessId.':'.$request->paymentId,
                'amount' => ['currency_code' => 'USD', 'value' => GatewayMoney::decimal($amount)],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => ['return_url' => $this->returnUrl, 'cancel_url' => $this->cancelUrl, 'user_action' => 'PAY_NOW']]],
        ]));

        if ($this->declined($response)) {
            return new ChargeResult($request->paymentId, PaymentStatus::Failed, 'USD', $amount);
        }

        $reference = $this->string($response->json('id'));
        $links = $response->json('links');
        $url = null;

        if (is_array($links)) {
            foreach ($links as $link) {
                if (is_array($link) && in_array($link['rel'] ?? null, ['approve', 'payer-action'], true) && is_string($link['href'] ?? null)) {
                    $url = $link['href'];
                    break;
                }
            }
        }

        if ($url === null || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'www.sandbox.paypal.com') {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        return new ChargeResult($reference, PaymentStatus::Pending, 'USD', $amount, $url, $reference);
    }

    public function status(ChargeReference $reference): ChargeResult
    {
        if ($reference->currency !== 'USD' || $reference->chargedAmount <= 0) {
            throw new InvalidValueException('payments.invalid_amount');
        }

        $response = $this->send(fn (PendingRequest $http): Response => $http->get('/v2/checkout/orders/'.rawurlencode($reference->providerReference)));

        if ($response->json('id') !== $reference->providerReference) {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        $this->validatePurchaseUnits($response);
        $this->validateAmount($response->json('purchase_units.0.amount'), $reference->chargedAmount);
        $captures = $response->json('purchase_units.0.payments.captures');

        if (is_array($captures) && $captures !== []) {
            return $this->captureResult($response, $reference);
        }

        if ($response->json('status') === 'APPROVED') {
            if (! $reference->captureAllowed) {
                return new ChargeResult($reference->providerReference, PaymentStatus::Pending, 'USD', $reference->chargedAmount, checkoutReference: $reference->providerReference);
            }
            $capture = $this->send(fn (PendingRequest $http): Response => $http->withHeaders(['PayPal-Request-Id' => $this->idempotencyKey($reference->paymentId, 'capture')])->withBody('{}', 'application/json')->post('/v2/checkout/orders/'.rawurlencode($reference->providerReference).'/capture'));

            if ($this->declined($capture)) {
                return new ChargeResult($reference->providerReference, PaymentStatus::Failed, 'USD', $reference->chargedAmount, checkoutReference: $reference->providerReference);
            }

            return $this->captureResult($capture, $reference);
        }

        $status = match ($response->json('status')) {
            'CREATED', 'SAVED', 'PAYER_ACTION_REQUIRED' => PaymentStatus::Pending,
            'VOIDED' => PaymentStatus::Expired,
            default => $this->unknownStatus($reference->paymentId),
        };

        return new ChargeResult($reference->providerReference, $status, 'USD', $reference->chargedAmount, checkoutReference: $reference->providerReference);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        throw new InvalidValueException('payments.refund_not_supported');
    }

    public function verifyWebhook(string $body, array $headers): bool
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

        if ($this->webhookId === '') {
            return false;
        }

        foreach (['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'] as $required) {
            if (! isset($headers[$required]) || $headers[$required] === '') {
                return false;
            }
        }

        $event = json_decode($body);

        if (! is_object($event)) {
            return false;
        }

        $verification = json_encode([
            'auth_algo' => $headers['paypal-auth-algo'],
            'cert_url' => $headers['paypal-cert-url'],
            'transmission_id' => $headers['paypal-transmission-id'],
            'transmission_sig' => $headers['paypal-transmission-sig'],
            'transmission_time' => $headers['paypal-transmission-time'],
            'webhook_id' => $this->webhookId,
        ], JSON_THROW_ON_ERROR);
        $payload = substr($verification, 0, -1).',"webhook_event":'.$body.'}';
        $response = $this->send(fn (PendingRequest $http): Response => $http->withBody($payload, 'application/json')->post('/v1/notifications/verify-webhook-signature'));

        return $response->json('verification_status') === 'SUCCESS';
    }

    /** @param Closure(PendingRequest): Response $request */
    private function send(Closure $request): Response
    {
        try {
            $token = Http::baseUrl($this->baseUrl)->withBasicAuth($this->clientId, $this->clientSecret)->acceptJson()->asForm()->timeout($this->timeout)->connectTimeout($this->connectTimeout)->withoutRedirecting()->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if (! $token->successful()) {
                throw new GatewayUnavailableException('payments.unavailable');
            }

            $response = $request(Http::baseUrl($this->baseUrl)->withToken($this->string($token->json('access_token')))->acceptJson()->asJson()->timeout($this->timeout)->connectTimeout($this->connectTimeout)->withoutRedirecting());
        } catch (ConnectionException) {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        if (! $response->successful() && ! $this->declined($response)) {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        return $response;
    }

    private function declined(Response $response): bool
    {
        return in_array($response->status(), [402, 422], true) && in_array($response->json('details.0.issue'), ['INSTRUMENT_DECLINED', 'TRANSACTION_REFUSED', 'PAYER_CANNOT_PAY', 'PAYMENT_DENIED'], true);
    }

    private function captureResult(Response $response, ChargeReference $reference): ChargeResult
    {
        if ($response->json('id') !== $reference->providerReference) {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        $this->validatePurchaseUnits($response);
        $captures = $response->json('purchase_units.0.payments.captures');

        if (! is_array($captures) || count($captures) !== 1 || ! is_array($captures[0] ?? null)) {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        $capture = $captures[0];
        $this->validateAmount($capture['amount'] ?? null, $reference->chargedAmount);
        $captureId = $this->string($capture['id'] ?? null);
        $status = match ($capture['status'] ?? null) {
            'COMPLETED' => PaymentStatus::Succeeded,
            'DECLINED', 'FAILED' => PaymentStatus::Failed,
            'PENDING' => PaymentStatus::Pending,
            default => $this->unknownStatus($reference->paymentId),
        };

        return new ChargeResult($reference->providerReference, $status, 'USD', $reference->chargedAmount, checkoutReference: $reference->providerReference, captureReference: $captureId);
    }

    private function validatePurchaseUnits(Response $response): void
    {
        $units = $response->json('purchase_units');

        if (! is_array($units) || count($units) !== 1) {
            throw new GatewayUnavailableException('payments.unavailable');
        }
    }

    private function validateAmount(mixed $amount, int $expected): void
    {
        if (! is_array($amount) || ($amount['currency_code'] ?? null) !== 'USD' || GatewayMoney::cents($amount['value'] ?? null) !== $expected) {
            throw new InvalidValueException('payments.invalid_amount');
        }
    }

    private function string(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        return $value;
    }

    private function idempotencyKey(string $paymentId, string $operation): string
    {
        return substr(hash('sha256', $paymentId.':'.$operation), 0, 32);
    }

    private function unknownStatus(string $paymentId): PaymentStatus
    {
        Log::warning('Unknown payment status received from gateway.', ['gateway' => 'paypal', 'payment_id' => $paymentId]);

        return PaymentStatus::Pending;
    }
}
