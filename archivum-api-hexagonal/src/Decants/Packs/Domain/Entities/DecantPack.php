<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\Entities;

use DateTimeImmutable;
use Src\Decants\Packs\Domain\Exceptions\DecantPackConflictException;
use Src\Decants\Packs\Domain\Exceptions\DecantPackPlanNotFoundException;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackPlanId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;

/**
 * A pack a customer bought (R33): prepaid decants that its redemptions take one by one, and never below 0 (R34). An
 * expired or exhausted pack cannot be used, refunded or changed for money (R35): this aggregate offers no operation
 * for either.
 */
final class DecantPack
{
    /** Hold the full state of a pack; the decants left always agree with its status. */
    private function __construct(
        public readonly DecantPackId $id,
        public readonly BusinessId $businessId,
        public readonly string $customerId,
        public readonly DecantPackPlanId $planId,
        public readonly int $totalDecants,
        public private(set) int $remainingDecants,
        public readonly int $mlPerDecant,
        public readonly ?MaxPricePerMl $maxPricePerMl,
        public private(set) DecantPackStatus $status,
        public private(set) DateTimeImmutable $expiresAt,
    ) {
        if ($totalDecants < 1 || $mlPerDecant < 1 || $remainingDecants < 0 || $remainingDecants > $totalDecants
            || ($status === DecantPackStatus::PendingPayment && $remainingDecants !== $totalDecants)
            || ($status === DecantPackStatus::Active && $remainingDecants === 0)
            || ($status === DecantPackStatus::Exhausted && $remainingDecants !== 0)) {
            throw new InvalidValueException('packs.invalid_pack');
        }
    }

    /**
     * Buy a pack from an offer on sale: it waits for its payment until the deadline of the store, and it copies the
     * decants, their ml and the cap of the offer, so that editing the offer later does not change it.
     *
     * @throws DecantPackPlanNotFoundException when the offer is no longer on sale
     */
    public static function create(DecantPackId $id, string $customerId, DecantPackPlan $plan, DateTimeImmutable $paymentDeadline): self
    {
        if (! $plan->isActive) {
            throw new DecantPackPlanNotFoundException('packs.plan_not_found');
        }

        return new self($id, $plan->businessId, $customerId, $plan->id, $plan->decants->value, $plan->decants->value,
            $plan->mlPerDecant->value, $plan->maxPricePerMl, DecantPackStatus::PendingPayment, $paymentDeadline);
    }

    /** Rebuild a pack from its stored state. */
    public static function reconstitute(DecantPackId $id, BusinessId $businessId, string $customerId, DecantPackPlanId $planId, int $totalDecants, int $remainingDecants, int $mlPerDecant, ?MaxPricePerMl $maxPricePerMl, DecantPackStatus $status, DateTimeImmutable $expiresAt): self
    {
        return new self($id, $businessId, $customerId, $planId, $totalDecants, $remainingDecants, $mlPerDecant, $maxPricePerMl, $status, $expiresAt);
    }

    /**
     * Its payment was confirmed (R33): the pack becomes active, and its validity counts from this moment until the same
     * hour once its days pass. Only a pack that waits for its payment is activated.
     */
    public function activate(DateTimeImmutable $at, ValidityDays $validity): void
    {
        if ($this->status !== DecantPackStatus::PendingPayment) {
            throw new DecantPackConflictException('packs.transition');
        }

        $this->status = DecantPackStatus::Active;
        $this->expiresAt = $at->modify('+'.$validity->days.' days');
    }

    /** Whether the clock already expired the pack: unpaid at its deadline, or active at the end of its validity. */
    public function isOverdueAt(DateTimeImmutable $at): bool
    {
        return in_array($this->status, [DecantPackStatus::PendingPayment, DecantPackStatus::Active], true) && $at >= $this->expiresAt;
    }

    /** Expire a pack whose deadline or validity elapsed, even with decants left. An exhausted pack never expires. */
    public function expire(DateTimeImmutable $at): void
    {
        if (! $this->isOverdueAt($at)) {
            throw new DecantPackConflictException('packs.transition');
        }

        $this->status = DecantPackStatus::Expired;
    }

    /** The status at a moment: a pack the clock already expired is expired, even before the queue processes it. */
    public function statusAt(DateTimeImmutable $at): DecantPackStatus
    {
        return $this->isOverdueAt($at) ? DecantPackStatus::Expired : $this->status;
    }

    /**
     * Take one decant of the pack for a decant of an order (R34). Only an active pack within its validity redeems, for
     * a decant of exactly its ml and, if it has a cap, of a presentation whose sale price fits under it (R32). The last
     * decant exhausts the pack.
     *
     * @throws DecantPackConflictException when the pack still waits for its payment, expired or has no decants left
     * @throws InvalidValueException when the decant has other ml or its presentation exceeds the cap
     */
    public function redeem(int $ml, int $salePrice, int $presentationMl, DateTimeImmutable $at): void
    {
        if ($this->statusAt($at) === DecantPackStatus::Expired) {
            throw new DecantPackConflictException('packs.expired');
        }

        if ($this->status === DecantPackStatus::Exhausted) {
            throw new DecantPackConflictException('packs.exhausted');
        }

        if ($this->status !== DecantPackStatus::Active) {
            throw new DecantPackConflictException('packs.not_active');
        }

        if ($ml !== $this->mlPerDecant) {
            throw new InvalidValueException('packs.ml_mismatch', ['ml' => $this->mlPerDecant]);
        }

        if ($this->maxPricePerMl !== null && ! $this->maxPricePerMl->allows($salePrice, $presentationMl)) {
            throw new InvalidValueException('packs.over_price_cap');
        }

        $this->remainingDecants--;
        if ($this->remainingDecants === 0) {
            $this->status = DecantPackStatus::Exhausted;
        }
    }
}
