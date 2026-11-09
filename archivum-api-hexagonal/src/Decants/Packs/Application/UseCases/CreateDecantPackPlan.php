<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use Src\Decants\Packs\Application\Contracts\DecantSizes;
use Src\Decants\Packs\Application\DTOs\CreateDecantPackPlanCommand;
use Src\Decants\Packs\Domain\Contracts\DecantPackPlanRepository;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantSizeNotSoldException;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class CreateDecantPackPlan
{
    /** Receive the repository, the decant sizes of the store, the business of the session and the transaction ports. */
    public function __construct(
        private DecantPackPlanRepository $plans,
        private DecantSizes $sizes,
        private BusinessContext $businessContext,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Define an offer in the business of the session (R32), on sale unless the owner asks otherwise.
     *
     * @throws DecantSizeNotSoldException when its ml are not one of the decant sizes of the store (R4)
     */
    public function execute(CreateDecantPackPlanCommand $command): DecantPackPlan
    {
        return $this->transaction->run(function () use ($command): DecantPackPlan {
            $plan = DecantPackPlan::create(
                $this->plans->nextId(),
                $this->businessContext->current(),
                new PlanName($command->name),
                new DecantCount($command->decants),
                MlPerDecant::soldBy($command->mlPerDecant, $this->sizes->current()),
                new PackPrice($command->price),
                new ValidityDays($command->validityDays),
                $command->maxPricePerMl === null ? null : new MaxPricePerMl($command->maxPricePerMl),
            );

            if (! $command->isActive) {
                $plan->deactivate();
            }

            $this->plans->save($plan);

            return $plan;
        });
    }
}
