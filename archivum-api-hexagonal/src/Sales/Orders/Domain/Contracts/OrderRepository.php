<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Domain\Contracts;

use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;

interface OrderRepository
{
    public function nextId(): OrderId;

    public function nextLineId(): string;

    public function find(OrderId $id, bool $forUpdate = false): ?Order;

    public function save(Order $order): void;

    /** Store the aggregate only while its row still has the status it was read with; answer whether it did. */
    public function saveIfStatus(Order $order, OrderStatus $expected): bool;
}
