<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Gateways\Onvo;

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

final readonly class OnvoGateway implements PaymentGateway
{
    public function __construct(
        private string $secretKey,
        private string $returnUrl,
        private string $cancelUrl,
        private string $webhookSecret = '',
        private int $timeout = 10,
        private int $connectTimeout = 3,
    ) {
        if (! str_starts_with($secretKey, 'onvo_test_') || $timeout <= 0 || $connectTimeout <= 0) {
            throw new InvalidValueException('payments.invalid_configuration');
        }
    }

    public function supportedMethods(): array
    {
        return [PaymentMethod::Card, PaymentMethod::SinpeMobile];
    }

    public function create(ChargeRequest $request): ChargeResult
    {
        $this->validateCharge($request);
        $amount = GatewayMoney::colonesToMinor($request->amount);
        $response = $this->send(fn (PendingRequest $http): Response => $http->post('/checkout/sessions/one-time-link', [
            'lineItems' => [['quantity' => 1, 'unitAmount' => $amount, 'currency' => 'CRC', 'description' => $request->paymentId]],
            'paymentMethodTypes' => [$request->method === PaymentMethod::SinpeMobile ? 'mobile_number' : 'card'],
            'metadata' => ['paymentId' => $request->paymentId, 'businessId' => $request->businessId],
            'redirectUrl' => $this->returnUrl,
            'cancelUrl' => $this->cancelUrl,
        ]));

        if ($response->status() === 402) {
            return new ChargeResult($request->paymentId, PaymentStatus::Failed, 'CRC', $request->amount);
        }

        $reference = $this->string($response->json('id'));
        $url = $this->string($response->json('url'));

        $this->requireTestMode($response);
        $this->validateOptionalAmount($response, $amount, 'amountTotal');

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! str_ends_with((string) parse_url($url, PHP_URL_HOST), '.onvopay.com')) {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        $intent = $response->json('paymentIntentId');

        return new ChargeResult($reference, PaymentStatus::Pending, 'CRC', $request->amount, $url, $reference, is_string($intent) && $intent !== '' ? $intent : null);
    }

    public function validateCharge(ChargeRequest $request): void
    {
        GatewayMoney::colonesToMinor($request->amount);

        if (! in_array($request->method, $this->supportedMethods(), true)) {
            throw new InvalidValueException('payments.unsupported_method');
        }
    }

    public function status(ChargeReference $reference): ChargeResult
    {
        if ($reference->currency !== 'CRC' || $reference->chargedAmount <= 0) {
            throw new InvalidValueException('payments.invalid_amount');
        }

        $sessionId = $reference->checkoutReference ?? $reference->providerReference;
        $session = $this->send(fn (PendingRequest $http): Response => $http->get('/checkout/sessions/'.rawurlencode($sessionId)));
        $this->requireTestMode($session);

        if ($session->json('id') !== $sessionId) {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        $this->validateOptionalAmount($session, GatewayMoney::colonesToMinor($reference->chargedAmount), 'amountTotal');
        $intentId = $session->json('paymentIntentId');

        if (! is_string($intentId) || $intentId === '') {
            $status = $session->json('status') === 'expired' ? PaymentStatus::Expired : PaymentStatus::Pending;

            return new ChargeResult($reference->providerReference, $status, 'CRC', $reference->chargedAmount, checkoutReference: $sessionId);
        }

        $intent = $this->send(fn (PendingRequest $http): Response => $http->get('/payment-intents/'.rawurlencode($intentId)));
        $this->requireTestMode($intent);

        if ($intent->json('id') !== $intentId || $intent->json('currency') !== 'CRC' || ! $this->sameAmount($intent->json('amount'), GatewayMoney::colonesToMinor($reference->chargedAmount))) {
            throw new InvalidValueException('payments.invalid_amount');
        }

        $status = match ($intent->json('status')) {
            'succeeded' => PaymentStatus::Succeeded,
            'canceled' => $session->json('status') === 'expired' ? PaymentStatus::Expired : $this->closeCheckout($reference, $sessionId, $intentId, PaymentStatus::Expired),
            'requires_payment_method' => ($intent->json('confirmationAttempts', 0) > 0 || $intent->json('lastPaymentError') !== null) ? $this->closeCheckout($reference, $sessionId, $intentId, PaymentStatus::Failed) : PaymentStatus::Pending,
            'requires_action', 'processing' => PaymentStatus::Pending,
            default => $this->unknownStatus($reference->paymentId),
        };

        return new ChargeResult($reference->providerReference, $status, 'CRC', $reference->chargedAmount, checkoutReference: $sessionId, captureReference: $intentId);
    }

    /** A declined method leaves an intent payable; close both remote resources before enabling another checkout. */
    private function closeCheckout(ChargeReference $reference, string $sessionId, string $intentId, PaymentStatus $closedStatus): PaymentStatus
    {
        try {
            $this->send(fn (PendingRequest $http): Response => $http->withBody('{}', 'application/json')->post('/checkout/sessions/'.rawurlencode($sessionId).'/expire'));
        } catch (GatewayUnavailableException) {
            // A lost response may still have expired the session; verify the remote state below.
        }

        try {
            $this->send(fn (PendingRequest $http): Response => $http->withBody('{}', 'application/json')->post('/payment-intents/'.rawurlencode($intentId).'/cancel'));
        } catch (GatewayUnavailableException) {
            // Cancellation can lose a race with a successful payment; the authoritative query decides.
        }

        $session = $this->send(fn (PendingRequest $http): Response => $http->get('/checkout/sessions/'.rawurlencode($sessionId)));
        $this->requireTestMode($session);

        if ($session->json('id') !== $sessionId || $session->json('paymentIntentId') !== $intentId) {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        $this->validateOptionalAmount($session, GatewayMoney::colonesToMinor($reference->chargedAmount), 'amountTotal');
        $intent = $this->send(fn (PendingRequest $http): Response => $http->get('/payment-intents/'.rawurlencode($intentId)));
        $this->requireTestMode($intent);

        if ($intent->json('id') !== $intentId || $intent->json('currency') !== 'CRC' || ! $this->sameAmount($intent->json('amount'), GatewayMoney::colonesToMinor($reference->chargedAmount))) {
            throw new InvalidValueException('payments.invalid_amount');
        }

        if ($intent->json('status') === 'succeeded') {
            return PaymentStatus::Succeeded;
        }

        if ($session->json('status') !== 'expired' || $intent->json('status') !== 'canceled') {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        return $closedStatus;
    }

    public function refund(RefundRequest $request): RefundResult
    {
        $amount = GatewayMoney::colonesToMinor($request->amount);
        $intentId = $request->captureReference;

        if ($intentId === null) {
            $session = $this->send(fn (PendingRequest $http): Response => $http->get('/checkout/sessions/'.rawurlencode($request->providerReference)));
            $this->requireTestMode($session);
            $intentId = $this->string($session->json('paymentIntentId'));
        }

        $refundId = $request->providerRefundReference;

        if ($refundId === null) {
            $response = $this->send(fn (PendingRequest $http): Response => $http->post('/refunds', [
                'paymentIntentId' => $intentId,
                'amount' => $amount,
                'description' => $request->refundId,
            ]));

            if ($response->status() === 402) {
                return new RefundResult($request->refundId, 'failed');
            }

            $refundId = $this->string($response->json('id'));
        }
        try {
            $refund = $this->send(fn (PendingRequest $http): Response => $http->get('/refunds/'.rawurlencode($refundId)));
        } catch (GatewayUnavailableException) {
            return new RefundResult($refundId, 'pending');
        }
        $this->requireTestMode($refund);

        if ($refund->json('id') !== $refundId || $refund->json('paymentIntentId') !== $intentId || ! $this->sameAmount($refund->json('amount'), $amount) || $refund->json('currency') !== 'CRC') {
            throw new InvalidValueException('payments.invalid_amount');
        }

        return new RefundResult($refundId, match ($refund->json('status')) {
            'succeeded' => 'succeeded',
            'failed', 'canceled' => 'failed',
            default => 'pending',
        });
    }

    public function verifyWebhook(string $body, array $headers): bool
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

        return $this->webhookSecret !== '' && isset($headers['x-webhook-secret']) && hash_equals($this->webhookSecret, $headers['x-webhook-secret']);
    }

    /** ONVO does not document a POST idempotency guarantee, so mutations are never retried here.
     * @param  Closure(PendingRequest): Response  $request
     */
    private function send(Closure $request): Response
    {
        try {
            $response = $request(Http::baseUrl('https://api.onvopay.com/v1')->withToken($this->secretKey)->acceptJson()->asJson()->timeout($this->timeout)->connectTimeout($this->connectTimeout)->withoutRedirecting());
        } catch (ConnectionException) {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        if (! $response->successful() && $response->status() !== 402) {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        return $response;
    }

    private function string(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw new GatewayUnavailableException('payments.unavailable');
        }

        return $value;
    }

    private function requireTestMode(Response $response): void
    {
        if ($response->json('mode') !== 'test') {
            throw new GatewayUnavailableException('payments.unavailable');
        }
    }

    private function validateOptionalAmount(Response $response, int $amount, string $field): void
    {
        if (($response->json($field) !== null && ! $this->sameAmount($response->json($field), $amount)) || ($response->json('currency') !== null && $response->json('currency') !== 'CRC')) {
            throw new InvalidValueException('payments.invalid_amount');
        }
    }

    private function sameAmount(mixed $amount, int $expected): bool
    {
        return (is_int($amount) || is_float($amount)) && is_finite((float) $amount) && $amount == $expected;
    }

    private function unknownStatus(string $paymentId): PaymentStatus
    {
        Log::warning('Unknown payment status received from gateway.', ['gateway' => 'onvo', 'payment_id' => $paymentId]);

        return PaymentStatus::Pending;
    }
}
