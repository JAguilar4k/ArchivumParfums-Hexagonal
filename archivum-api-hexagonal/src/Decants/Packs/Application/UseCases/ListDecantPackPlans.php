<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use Src\Decants\Packs\Domain\Contracts\DecantPackPlanRepository;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;

final readonly class ListDecantPackPlans
{
    /** Receive the repository, which only reaches the business of the session. */
    public function __construct(private DecantPackPlanRepository $plans) {}

    /**
     * List every offer of the business of the session, on sale or not, by name.
     *
     * @return list<DecantPackPlan>
     */
    public function execute(): array
    {
        return $this->plans->all();
    }
}
