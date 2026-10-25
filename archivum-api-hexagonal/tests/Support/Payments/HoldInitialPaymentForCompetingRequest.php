<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;

/** Observe the real competing PostgreSQL lock before the initial attempt commits. */
final class HoldInitialPaymentForCompetingRequest implements PaymentRepository
{
    public bool $observedOverlap = false;

    public function __construct(private readonly PaymentRepository $repository) {}

    public function nextId(): PaymentId
    {
        return $this->repository->nextId();
    }

    public function find(PaymentId $id, bool $forUpdate = false): ?Payment
    {
        return $this->repository->find($id, $forUpdate);
    }

    public function pending(Payable $payable, PaymentPurpose $purpose): ?Payment
    {
        return $this->repository->pending($payable, $purpose);
    }

    public function save(Payment $payment): void
    {
        $this->repository->save($payment);
        if ($payment->providerReference !== null) {
            return;
        }
        $deadline = microtime(true) + 10;
        do {
            DB::statement('select pg_stat_clear_snapshot()');
            $this->observedOverlap = (bool) DB::scalar(<<<'SQL'
                select exists (select 1 from pg_stat_activity
                    where datname = current_database() and pg_backend_pid() = any (pg_blocking_pids(pid)))
                SQL);
            if (! $this->observedOverlap) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('The competing payment operation never waited for the order lock.');
                }
                usleep(10000);
            }
        } while (! $this->observedOverlap);
    }
}
