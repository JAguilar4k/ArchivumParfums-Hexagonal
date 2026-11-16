<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use Src\Decants\Packs\Application\Contracts\DecantSizes;
use Src\Decants\Packs\Application\DTOs\UpdateDecantPackPlanCommand;
use Src\Decants\Packs\Domain\Contracts\DecantPackPlanRepository;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantPackPlanNotFoundException;
use Src\Decants\Packs\Domain\Exceptions\DecantSizeNotSoldException;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackPlanId;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class UpdateDecantPackPlan
{
    /** Receive the repository, which only reaches the business of the session, the decant sizes of the store and the transaction ports. */
    public function __construct(
        private DecantPackPlanRepository $plans,
        private DecantSizes $sizes,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Apply the requested changes to an offer of the business of the session, also to take it off sale or put it back.
     * The packs already bought keep what they copied from it; an offer of another business is treated as missing.
     *
     * @throws DecantPackPlanNotFoundException
     * @throws DecantSizeNotSoldException when the new ml are not one of the decant sizes of the store (R4)
     */
    public function execute(UpdateDecantPackPlanCommand $command): DecantPackPlan
    {
        return $this->transaction->run(function () use ($command): DecantPackPlan {
            $plan = $this->plans->find($this->idOf($command->planId), forUpdate: true)
                ?? throw new DecantPackPlanNotFoundException('packs.plan_not_found');

            $changes = $command->changes;

            $plan->revise(
                isset($changes['name']) ? new PlanName($changes['name']) : $plan->name,
                isset($changes['decants']) ? new DecantCount($changes['decants']) : $plan->decants,
                $this->mlPerDecant($plan, $changes['ml_per_decant'] ?? null),
                isset($changes['price']) ? new PackPrice($changes['price']) : $plan->price,
                isset($changes['validity_days']) ? new ValidityDays($changes['validity_days']) : $plan->validity,
                $this->cap($plan, $changes),
            );

            match ($changes['active'] ?? null) {
                true => $plan->reactivate(),
                false => $plan->deactivate(),
                null => null,
            };

            $this->plans->save($plan);

            return $plan;
        });
    }

    /** An identity that is not a valid one cannot belong to any offer. */
    private function idOf(string $id): DecantPackPlanId
    {
        try {
            return new DecantPackPlanId($id);
        } catch (InvalidValueException) {
            throw new DecantPackPlanNotFoundException('packs.plan_not_found');
        }
    }

    /**
     * New ml must be one of the decant sizes of the store. The ones the offer already has stay valid even if the
     * store stopped selling that size, so editing another field never forces a change of ml.
     */
    private function mlPerDecant(DecantPackPlan $plan, ?int $ml): MlPerDecant
    {
        if ($ml === null || $ml === $plan->mlPerDecant->value) {
            return $plan->mlPerDecant;
        }

        return MlPerDecant::soldBy($ml, $this->sizes->current());
    }

    /**
     * The cap sent, none when it was sent empty, or the current one when it was not sent.
     *
     * @param  array{max_price_per_ml?: int|null}  $changes
     */
    private function cap(DecantPackPlan $plan, array $changes): ?MaxPricePerMl
    {
        if (! array_key_exists('max_price_per_ml', $changes)) {
            return $plan->maxPricePerMl;
        }

        return $changes['max_price_per_ml'] === null ? null : new MaxPricePerMl($changes['max_price_per_ml']);
    }
}
