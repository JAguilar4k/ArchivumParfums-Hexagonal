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
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineKind;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class SalesPayableReader implements PayableReader
{
    public function __construct(private OrderRepository $orders, private BusinessRepository $businesses, private BusinessContext $business, private RefundRequests $refunds, private ExchangeRates $exchangeRates) {}

    public function findForPayment(string $orderId, string $userId, bool $startingPayment = true): PayableOrder
    {
        $order = $this->orders->find(new OrderId($orderId), true) ?? throw new PaymentNotFoundException('payments.not_found');
        if (! $this->isCustomerOf($order, $userId)) {
            throw new PaymentNotFoundException('payments.not_found');
        }
        if ($startingPayment && $order->status->value === 'pending_payment' && $order->expiresAt <= new DateTimeImmutable) {
            throw new InvalidPaymentTransitionException('payments.not_payable');
        }

        return $this->payable($order, $startingPayment);
    }

    public function isOwnedBy(string $orderId, string $userId): bool
    {
        $order = $this->orders->find(new OrderId($orderId));

        return $order !== null && $this->isCustomerOf($order, $userId);
    }

    public function findForUpdate(string $orderId): PayableOrder
    {
        $order = $this->orders->find(new OrderId($orderId), true) ?? throw new PaymentNotFoundException('payments.not_found');

        return $this->payable($order, startingPayment: false);
    }

    /** Whether the user is the customer who placed the order in the current business. */
    private function isCustomerOf(Order $order, string $userId): bool
    {
        return CustomerModel::query()->where('business_id', $this->business->current()->value)->whereKey($order->customerId)->where('user_id', $userId)->exists();
    }

    /** What payments need to know about a locked order; only a new PayPal charge needs the exchange rate. */
    private function payable(Order $order, bool $startingPayment): PayableOrder
    {
        $businessId = $this->business->current();
        $business = $this->businesses->find($businessId) ?? throw new PaymentNotFoundException('payments.not_found');
        $rate = $startingPayment && $business->settings->defaultGateway === 'paypal' ? $this->exchangeRates->current() : null;

        return new PayableOrder($order->id->value, $businessId->value, $order->status->value, $order->amounts->dueNow, $order->amounts->subtotal, $order->amounts->paid, array_any($order->lines, fn ($line): bool => $line->kind === OrderLineKind::Bottle), $business->settings->defaultGateway, $rate?->value, $this->refunds->hasPendingRefunds($order->id->value));
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
