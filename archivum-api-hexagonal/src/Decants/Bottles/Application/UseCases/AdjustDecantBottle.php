<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\UseCases;

use Src\Decants\Bottles\Application\Contracts\BottleReader;
use Src\Decants\Bottles\Application\DTOs\MovementView;
use Src\Decants\Bottles\Domain\Contracts\MlReservation;
use Src\Decants\Bottles\Domain\Exceptions\BottleNotFoundException;
use Src\Shared\Domain\Contracts\AuditTrail;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\AuditAction;

final readonly class AdjustDecantBottle
{
    public function __construct(
        private MlReservation $stock,
        private BottleReader $reader,
        private AuditTrail $audit,
        private BusinessContext $business,
        private TransactionRunner $transaction,
    ) {}

    public function execute(string $id, int $ml, string $reason, string $userId): MovementView
    {
        return $this->transaction->run(function () use ($id, $ml, $reason, $userId): MovementView {
            $bottle = $this->reader->find($id) ?? throw BottleNotFoundException::create();
            $movement = $this->reader->movement($this->stock->adjust($id, $ml, $reason, $userId));

            $this->audit->record(
                $this->business->current(),
                AuditAction::BottleAdjusted,
                'decant_bottle',
                $id,
                ['remaining_ml' => $bottle->remainingMl],
                ['remaining_ml' => $bottle->remainingMl + $ml, 'reason' => $reason],
            );

            return $movement;
        });
    }
}
