<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Domain\Contracts;

use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;

interface OrderRepository
{
    public function nextId(): OrderId;

    public function nextLineId(): string;

    public function find(OrderId $id, bool $forUpdate = false): ?Order;

    public function save(Order $order): void;
}
