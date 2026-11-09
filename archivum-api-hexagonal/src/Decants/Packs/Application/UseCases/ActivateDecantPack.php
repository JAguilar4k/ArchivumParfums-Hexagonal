<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use DateTimeImmutable;
use Src\Decants\Packs\Application\DTOs\ActivateDecantPackCommand;
use Src\Decants\Packs\Domain\Contracts\DecantPackPlanRepository;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\Exceptions\DecantPackConflictException;
use Src\Decants\Packs\Domain\Exceptions\DecantPackNotFoundException;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ActivateDecantPack
{
    /** Receive the packs and the offers of the business of the operation and the transaction ports. */
    public function __construct(
        private DecantPackRepository $packs,
        private DecantPackPlanRepository $plans,
        private TransactionRunner $transaction,
    ) {}

    /**
     * The payment of a pack was confirmed, by a verified notice of the gateway (#94) or by the reconciliation (#96),
     * never by what the browser says: the pack becomes active and its validity counts from this moment until the same
     * hour once the days of its offer pass (R33).
     *
     * @throws DecantPackNotFoundException when the pack is not of the business of the operation
     * @throws DecantPackConflictException when the pack no longer waits for its payment
     */
    public function execute(ActivateDecantPackCommand $command): DecantPack
    {
        return $this->transaction->run(function () use ($command): DecantPack {
            $pack = $this->packs->find($this->idOf($command->packId), forUpdate: true)
                ?? throw new DecantPackNotFoundException('packs.pack_not_found');
            // The offer is never deleted, so the one the pack was bought from is always there.
            $plan = $this->plans->find($pack->planId) ?? throw new DecantPackNotFoundException('packs.pack_not_found');

            $pack->activate(new DateTimeImmutable($command->activatedAt), $plan->validity);

            // Besides the lock, the row changes only while it still waits for its payment, so the activation and the
            // expiry of the pack that arrive at the same time end with only one of the two (#159).
            if (! $this->packs->saveIfStatus($pack, DecantPackStatus::PendingPayment)) {
                throw new DecantPackConflictException('packs.transition');
            }

            return $pack;
        });
    }

    /** An identifier that is not one of a pack names no pack of the business. */
    private function idOf(string $id): DecantPackId
    {
        try {
            return new DecantPackId($id);
        } catch (InvalidValueException) {
            throw new DecantPackNotFoundException('packs.pack_not_found');
        }
    }
}
