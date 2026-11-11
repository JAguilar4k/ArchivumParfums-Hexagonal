<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use DateTimeImmutable;
use Src\Decants\Packs\Application\DTOs\ReturnPackDecantCommand;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class ReturnPackDecant
{
    /** Receive the packs of the business of the operation and the transaction port. */
    public function __construct(
        private DecantPackRepository $packs,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Give back to its pack the decant a line of a cancelled or expired order had redeemed, in the transaction that
     * closes the order (precisions of R32–R35). The redemption is forgotten under the lock of the pack, so a decant
     * goes back only once; a line without a redemption of that pack gives nothing back.
     */
    public function execute(ReturnPackDecantCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $pack = $this->packs->find(new DecantPackId($command->packId), forUpdate: true);
            if ($pack === null || ! $this->packs->removeRedemption($pack->id, $command->orderLineId)) {
                return;
            }

            $pack->returnDecant(new DateTimeImmutable($command->returnedAt));
            $this->packs->save($pack);
        });
    }
}
