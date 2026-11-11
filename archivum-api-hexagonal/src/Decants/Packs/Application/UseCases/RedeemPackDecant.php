<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use DateTimeImmutable;
use Src\Decants\Packs\Application\DTOs\RedeemPackDecantCommand;
use Src\Decants\Packs\Domain\Contracts\DecantPackBalance;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\Exceptions\DecantPackConflictException;
use Src\Decants\Packs\Domain\Exceptions\DecantPackNotFoundException;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class RedeemPackDecant
{
    /** Receive the packs of the business of the operation, their balance in PostgreSQL and the transaction port. */
    public function __construct(
        private DecantPackRepository $packs,
        private DecantPackBalance $balance,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Take one decant of a pack of the customer for a decant of an order (R34), in the transaction of the order and
     * before its lines are saved. The pack that was read checks the decant against what it bought, and then PostgreSQL
     * takes it with a conditional UPDATE: of two orders for the last decant, the second waits for the row of the first
     * and finds no decant left, whatever its copy of the pack said. The row stays locked until the transaction of the
     * order ends. RecordPackRedemption keeps the line afterwards.
     *
     * @throws DecantPackNotFoundException when the pack is not one of the customer in the business of the operation
     * @throws DecantPackConflictException when the pack waits for its payment, expired or has no decants left
     * @throws InvalidValueException when the decant has other ml than the pack or its presentation exceeds the cap
     */
    public function execute(RedeemPackDecantCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $pack = $this->packs->find($this->idOf($command->packId));
            if ($pack === null || $pack->customerId !== $command->customerId) {
                throw new DecantPackNotFoundException('packs.pack_not_found');
            }

            $at = new DateTimeImmutable($command->redeemedAt);
            $pack->redeem($command->ml, $command->salePrice, $command->presentationMl, $at);

            if (! $this->balance->take($pack->id, $at)) {
                // Another order took the last decant, or the pack was expired, after this one read it.
                throw new DecantPackConflictException($this->packs->find($pack->id)?->statusAt($at) === DecantPackStatus::Expired ? 'packs.expired' : 'packs.exhausted');
            }
        });
    }

    /** An identifier that is not one of a pack names no pack of the customer. */
    private function idOf(string $id): DecantPackId
    {
        try {
            return new DecantPackId($id);
        } catch (InvalidValueException) {
            throw new DecantPackNotFoundException('packs.pack_not_found');
        }
    }
}
