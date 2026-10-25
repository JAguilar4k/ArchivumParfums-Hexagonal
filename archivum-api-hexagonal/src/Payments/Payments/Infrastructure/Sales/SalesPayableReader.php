<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Sales;

use DateTimeImmutable;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\DTOs\PayableOrder;
use Src\Payments\Payments\Domain\Contracts\ExchangeRates;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Sales\Orders\Application\Contracts\RefundRequests;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineKind;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class SalesPayableReader implements PayableReader
{
    public function __construct(private OrderRepository $orders, private BusinessRepository $businesses, private BusinessContext $business, private RefundRequests $refunds, private ExchangeRates $exchangeRates) {}

    public function findForPayment(string $orderId, string $userId, bool $startingPayment = true): PayableOrder
    {
        $businessId = $this->business->current();
        $order = $this->orders->find(new OrderId($orderId), true) ?? throw new PaymentNotFoundException('payments.not_found');
        $customer = CustomerModel::query()->where('business_id', $businessId->value)->whereKey($order->customerId)->where('user_id', $userId)->exists();
        if (! $customer) {
            throw new PaymentNotFoundException('payments.not_found');
        }
        if ($startingPayment && $order->status->value === 'pending_payment' && $order->expiresAt <= new DateTimeImmutable) {
            throw new InvalidPaymentTransitionException('payments.not_payable');
        }

        $business = $this->businesses->find($businessId) ?? throw new PaymentNotFoundException('payments.not_found');
        $rate = $startingPayment && $business->settings->defaultGateway === 'paypal' ? $this->exchangeRates->current() : null;

        return new PayableOrder($orderId, $businessId->value, $order->status->value, $order->amounts->dueNow, $order->amounts->subtotal, $order->amounts->paid, array_any($order->lines, fn ($line): bool => $line->kind === OrderLineKind::Bottle), $business->settings->defaultGateway, $rate?->value, $this->refunds->hasPendingRefunds($orderId));
    }

    public function applyPayment(string $orderId, int $amount): void
    {
        $order = $this->orders->find(new OrderId($orderId), true) ?? throw new PaymentNotFoundException('payments.not_found');
        $order->recordPayment($amount);
        $this->orders->save($order);
    }

    public function applyRefund(string $orderId, int $amount): void
    {
        $order = $this->orders->find(new OrderId($orderId), true) ?? throw new PaymentNotFoundException('payments.not_found');
        $order->recordRefund($amount);
        $this->orders->save($order);
    }

    public function applyLatePayment(string $orderId, int $amount): void
    {
        $order = $this->orders->find(new OrderId($orderId), true) ?? throw new PaymentNotFoundException('payments.not_found');
        $order->recordLatePayment($amount);
        $this->orders->save($order);
        $this->refunds->request($orderId, $amount);
    }
}
