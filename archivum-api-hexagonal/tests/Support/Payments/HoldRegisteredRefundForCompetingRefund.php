<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Payments\Payments\Domain\Contracts\RefundRepository;
use Src\Payments\Payments\Domain\Entities\Refund;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\RefundId;

/**
 * Keeps open the transaction that registered a refund until another backend waits for it, as when two refunds of the
 * same payment arrive at the same time: the second one then reaches the lock of the payment while the first one is not
 * committed. Without that lock both would count the pending refunds at once, both would wait here, and the test fails.
 */
final class HoldRegisteredRefundForCompetingRefund implements RefundRepository
{
    public bool $observedOverlap = false;

    public function __construct(private readonly RefundRepository $refunds) {}

    public function nextId(): RefundId
    {
        return $this->refunds->nextId();
    }

    public function find(RefundId $id, bool $forUpdate = false): ?Refund
    {
        return $this->refunds->find($id, $forUpdate);
    }

    public function findByReference(string $gateway, string $reference): ?Refund
    {
        return $this->refunds->findByReference($gateway, $reference);
    }

    public function pendingAmountOf(PaymentId $paymentId): int
    {
        return $this->refunds->pendingAmountOf($paymentId);
    }

    public function inFlightAmountOf(PaymentId $paymentId): int
    {
        return $this->refunds->inFlightAmountOf($paymentId);
    }

    public function ofOrder(string $orderId): array
    {
        return $this->refunds->ofOrder($orderId);
    }

    public function add(Refund $refund): void
    {
        $this->refunds->add($refund);

        $deadline = microtime(true) + 10;
        do {
            DB::statement('select pg_stat_clear_snapshot()');
            $this->observedOverlap = (bool) DB::scalar(<<<'SQL'
                select exists (select 1 from pg_stat_activity
                    where datname = current_database() and pg_backend_pid() = any (pg_blocking_pids(pid)))
                SQL);
            if (! $this->observedOverlap) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('The competing refund never waited for the registered one.');
                }
                usleep(10000);
            }
        } while (! $this->observedOverlap);
    }

    public function save(Refund $refund): void
    {
        $this->refunds->save($refund);
    }

    public function saveIfPending(Refund $refund): bool
    {
        return $this->refunds->saveIfPending($refund);
    }
}
