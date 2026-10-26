<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Contracts\RefundRepository;
use Src\Payments\Payments\Domain\Entities\Refund;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\RefundRequest;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class ProcessOrderRefunds
{
    public function __construct(private RefundRepository $refunds, private PaymentRepository $payments, private PaymentGateways $gateways, private PayableReader $orders, private TransactionRunner $transaction) {}

    /** @return list<array{id: string, monto: int, estado: string}> */
    public function execute(string $orderId): array
    {
        $ids = $this->transaction->run(fn (): array => array_map(fn (Refund $refund): string => $refund->id, $this->refunds->pendingForOrder($orderId)));
        $results = [];

        foreach ($ids as $id) {
            $attempt = $this->transaction->run(function () use ($orderId, $id): ?array {
                $refund = array_find($this->refunds->pendingForOrder($orderId), fn (Refund $row): bool => $row->id === $id);
                if ($refund === null) {
                    return null;
                }
                $payment = $this->payments->find(new PaymentId($refund->paymentId), true) ?? throw new PaymentNotFoundException('payments.not_found');
                if ($payment->gateway !== 'onvo' || ($refund->attempted && $refund->providerReference === null)) {
                    return [$refund, $payment, false];
                }
                $refund->start();
                $this->refunds->save($refund);

                return [$refund, $payment, true];
            });
            if ($attempt === null) {
                continue;
            }
            [$refund, $payment, $maySend] = $attempt;
            if (! $maySend) {
                $results[] = $this->view($refund);

                continue;
            }

            // Commit attempted_at before the non-idempotent POST, just as StartPayment commits its intent.
            try {
                $result = $this->gateways->for($payment->gateway)->refund(new RefundRequest($refund->id, $payment->providerReference ?? '', $refund->amount, $payment->captureReference, $payment->exchangeRate, $refund->providerReference));
            } catch (GatewayUnavailableException) {
                $results[] = $this->view($refund);

                continue;
            }

            // Keep the remote reference even when applying its financial effect later rolls back.
            $this->transaction->run(function () use ($orderId, $id, $result): void {
                $fresh = array_find($this->refunds->pendingForOrder($orderId), fn (Refund $row): bool => $row->id === $id);
                if ($fresh !== null) {
                    $fresh->record($result->providerReference, 'pending');
                    $this->refunds->save($fresh);
                }
            });

            $completed = $this->transaction->run(function () use ($orderId, $id, $result): ?Refund {
                $fresh = array_find($this->refunds->pendingForOrder($orderId), fn (Refund $row): bool => $row->id === $id);
                if ($fresh === null) {
                    return null;
                }
                $payment = $this->payments->find(new PaymentId($fresh->paymentId), true) ?? throw new PaymentNotFoundException('payments.not_found');
                $fresh->record($result->providerReference, $result->status);
                if ($fresh->status === 'succeeded') {
                    $payment->refund($fresh->amount);
                    $this->payments->save($payment);
                    $this->orders->applyRefund($orderId, $fresh->amount);
                }
                $this->refunds->save($fresh);

                return $fresh;
            });
            if ($completed !== null) {
                $results[] = $this->view($completed);
            }
        }

        return $results;
    }

    /** @return array{id: string, monto: int, estado: string} */
    private function view(Refund $refund): array
    {
        return ['id' => $refund->id, 'monto' => $refund->amount, 'estado' => match ($refund->status) {
            'succeeded' => 'confirmado', 'failed' => 'fallido', default => 'pendiente'
        }];
    }
}
