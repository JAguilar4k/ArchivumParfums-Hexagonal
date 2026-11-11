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

    /** Find an offer of the business of the operation; for update, it stays locked until the transaction ends. */
    public function find(DecantPackPlanId $id, bool $forUpdate = false): ?DecantPackPlan;

    /**
     * List every offer of the business of the operation, on sale or not, by name.
     *
     * @return list<DecantPackPlan>
     */
    public function all(): array;
}
