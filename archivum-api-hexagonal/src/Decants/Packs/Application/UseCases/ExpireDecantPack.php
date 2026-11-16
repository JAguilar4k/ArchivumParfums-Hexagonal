<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use DateTimeImmutable;
use Src\Decants\Packs\Application\Contracts\PackPayments;
use Src\Decants\Packs\Application\DTOs\ExpireDecantPackCommand;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\Exceptions\DecantPackConflictException;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\ExpiryReason;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class ExpireDecantPack
{
    /** Receive the packs of the business of the operation, their payments and the transaction port. */
    public function __construct(
        private DecantPackRepository $packs,
        private PackPayments $payments,
        private TransactionRunner $transaction,
    ) {}

    /**
     * When a deadline of a pack arrives (#159): a pack that still waits for its payment expires together with its
     * pending payment, and an active pack expires at the end of its validity even with decants left. Each reason
     * expires only the packs of its status, so an exhausted or expired pack does not change and a second run changes
     * nothing. Expiring a pack never refunds it (R35). Answer whether the pack expired.
     */
    public function execute(ExpireDecantPackCommand $command): bool
    {
        $reason = ExpiryReason::from($command->reason);
        $checkedAt = new DateTimeImmutable($command->checkedAt);

        return $this->transaction->run(function () use ($command, $reason, $checkedAt): bool {
            // The lock makes a confirmation of its payment (#94) wait for this transaction, or this one wait for it.
            $pack = $this->packs->find(new DecantPackId($command->packId), forUpdate: true);

            if ($pack === null || $pack->status !== $reason->status() || ! $pack->isOverdueAt($checkedAt)) {
                return false;
            }

            $pack->expire($checkedAt);

            if ($reason === ExpiryReason::Unpaid) {
                $this->payments->expirePending($pack->id->value);
            }

            // Besides the lock, the row changes only while it still has the status this reason expires. If another
            // operation changed it first, the transaction is undone, together with the payment it closed.
            if (! $this->packs->saveIfStatus($pack, $reason->status())) {
                throw new DecantPackConflictException('packs.transition');
            }

            return true;
        });
    }
}
