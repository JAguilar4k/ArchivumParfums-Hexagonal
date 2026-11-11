<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\Contracts;

use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackPlanId;

interface DecantPackPlanRepository
{
    /** Generate the identity of a new offer. */
    public function nextId(): DecantPackPlanId;

    /** Persist a new offer, or the changes of an existing one, in the business of the operation. */
    public function save(DecantPackPlan $plan): void;

    /** Find an offer of the business of the operation. */
    public function find(DecantPackPlanId $id): ?DecantPackPlan;
}
