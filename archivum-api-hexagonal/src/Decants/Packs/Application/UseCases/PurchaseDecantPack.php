<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use DateTimeImmutable;
use Src\Decants\Packs\Application\Contracts\PackCustomers;
use Src\Decants\Packs\Application\Contracts\PackPayments;
use Src\Decants\Packs\Application\Contracts\PaymentDeadline;
use Src\Decants\Packs\Application\DTOs\PurchaseDecantPackCommand;
use Src\Decants\Packs\Application\DTOs\PurchasedPack;
use Src\Decants\Packs\Domain\Contracts\DecantPackPlanRepository;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\Exceptions\DecantPackPlanNotFoundException;
use Src\Decants\Packs\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackPlanId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Domain\ValueObjects\IdempotencyKey;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class PurchaseDecantPack
{
    /** Receive the packs and the offers of the store of the address, its customers, its payment deadline, the payments and the transaction ports. */
    public function __construct(
        private DecantPackRepository $packs,
        private DecantPackPlanRepository $plans,
        private PackCustomers $customers,
        private PaymentDeadline $deadline,
        private PackPayments $payments,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Buy a pack of an offer on sale for the account of the session, which becomes a customer of the store if it was
     * not yet (R33). The pack waits for its payment until the deadline of the store, and the payment of the full price
     * of the stored offer starts once the pack is saved, never inside its transaction. The same purchase sent again
     * with the same key answers the pack already bought and its payment, without a second charge, also when both
     * requests arrive at once; the same key for another offer is refused. A purchase without a key, or with another
     * one, is a new one.
     *
     * @throws DecantPackPlanNotFoundException when the offer does not exist in the store or is not on sale
     * @throws IdempotencyKeyReusedException when the key was used to buy another offer
     */
    public function execute(PurchaseDecantPackCommand $command): PurchasedPack
    {
        $planId = $this->planIdOf($command->planId);
        $key = $command->idempotencyKey === null ? null : new IdempotencyKey($command->idempotencyKey);
        $at = new DateTimeImmutable($command->purchasedAt);

        try {
            [$pack, $created] = $this->transaction->run(fn (): array => $this->purchase($planId, $key, $command->userId, $at));
        } catch (IdempotencyKeyReusedException $exception) {
            // Another request with the same key won the race: this transaction rolled back and the winner is answered.
            [$pack, $created] = $this->transaction->run(fn (): array => $this->previousPurchase($this->customers->findOrCreate($command->userId), $planId, $key) ?? throw $exception);
        }

        // A pack that still waits starts its payment or answers the attempt already pending; one bought before that no
        // longer waits answers its last attempt, so sending the purchase again never charges it twice.
        $payment = $pack->statusAt($at) === DecantPackStatus::PendingPayment
            ? $this->payments->start($pack->id->value, $command->userId, $command->method)
            : $this->payments->last($pack->id->value);

        return new PurchasedPack($pack, $payment, $created);
    }

    /**
     * The pack of this purchase sent again, or a new pack of the offer on sale.
     *
     * @return array{DecantPack, bool} the pack and whether this purchase created it
     */
    private function purchase(DecantPackPlanId $planId, ?IdempotencyKey $key, string $userId, DateTimeImmutable $at): array
    {
        $customerId = $this->customers->findOrCreate($userId);
        $previous = $this->previousPurchase($customerId, $planId, $key);

        if ($previous !== null) {
            return $previous;
        }

        $plan = $this->plans->find($planId) ?? throw new DecantPackPlanNotFoundException('packs.plan_not_found');
        $pack = DecantPack::create($this->packs->nextId(), $customerId, $plan, $at->modify('+'.$this->deadline->minutes().' minutes'), $key);
        $this->packs->add($pack);

        return [$pack, true];
    }

    /**
     * The pack the customer already bought with this key, as long as it is of the same offer.
     *
     * @return array{DecantPack, false}|null
     *
     * @throws IdempotencyKeyReusedException when the key bought another offer
     */
    private function previousPurchase(string $customerId, DecantPackPlanId $planId, ?IdempotencyKey $key): ?array
    {
        if ($key === null) {
            return null;
        }

        $previous = $this->packs->findByIdempotencyKey($customerId, $key);

        if ($previous === null) {
            return null;
        }

        if (! $previous->repeatsPurchase($key, $planId)) {
            throw IdempotencyKeyReusedException::create();
        }

        return [$previous, false];
    }

    /** An identifier that is not one of an offer names no offer of the store. */
    private function planIdOf(string $id): DecantPackPlanId
    {
        try {
            return new DecantPackPlanId($id);
        } catch (InvalidValueException) {
            throw new DecantPackPlanNotFoundException('packs.plan_not_found');
        }
    }
}
