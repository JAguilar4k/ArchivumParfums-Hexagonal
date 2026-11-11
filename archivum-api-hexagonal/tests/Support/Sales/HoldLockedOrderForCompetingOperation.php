<?php

declare(strict_types=1);

namespace Tests\Support\Sales;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;

/**
 * Makes two operations on the same order compete in a known order, as when a provider's notice confirms a charge while
 * the customer cancels or while the order expires. The first one locks the order, creates the file it was given so the
 * other one starts, and keeps its transaction open until another backend waits for it. The other one waits for that
 * file before it starts, and is given none. Without the lock of the order the other one would never wait here, and the
 * test fails.
 */
final class HoldLockedOrderForCompetingOperation implements OrderRepository
{
    public bool $observedOverlap = false;

    private bool $held = false;

    public function __construct(private readonly OrderRepository $orders, private readonly ?string $lockedSignal) {}

    /** Wait until the first operation holds the lock of the order, so the other one starts after it. */
    public static function waitUntilLocked(string $lockedSignal): void
    {
        $deadline = microtime(true) + 10;
        while (! file_exists($lockedSignal)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('The first operation never locked the order.');
            }
            usleep(10000);
        }
    }

    public function nextId(): OrderId
    {
        return $this->orders->nextId();
    }

    public function nextLineId(): string
    {
        return $this->orders->nextLineId();
    }

    public function find(OrderId $id, bool $forUpdate = false): ?Order
    {
        $order = $this->orders->find($id, $forUpdate);

        if ($forUpdate && $this->lockedSignal !== null && ! $this->held) {
            $this->held = true;
            if (! touch($this->lockedSignal)) {
                throw new RuntimeException('Could not tell the other operation that the order is locked.');
            }
            $this->waitForTheCompetingOperation();
        }

        return $order;
    }

    public function save(Order $order): void
    {
        $this->orders->save($order);
    }

    public function saveIfStatus(Order $order, OrderStatus $expected): bool
    {
        return $this->orders->saveIfStatus($order, $expected);
    }

    /** With the order locked, wait until another backend waits for this one. */
    private function waitForTheCompetingOperation(): void
    {
        $deadline = microtime(true) + 10;
        do {
            DB::statement('select pg_stat_clear_snapshot()');
            $this->observedOverlap = (bool) DB::scalar(<<<'SQL'
                select exists (select 1 from pg_stat_activity
                    where datname = current_database() and pg_backend_pid() = any (pg_blocking_pids(pid)))
                SQL);
            if (! $this->observedOverlap) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('The competing operation never waited for the locked order.');
                }
                usleep(10000);
            }
        } while (! $this->observedOverlap);
    }
}
