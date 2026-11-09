<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use Src\Decants\Packs\Domain\Contracts\DecantPackPlanRepository;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackPlanId;
use Src\Shared\Domain\Contracts\BusinessContext;

/** Offers kept in memory, each one reachable only from its own business, like the adapter that filters by it. */
final class InMemoryDecantPackPlanRepository implements DecantPackPlanRepository
{
    /** @var array<string, DecantPackPlan> */
    private array $plans = [];

    /** @var list<string> identifiers of the offers read to be changed, in the order they were read */
    public private(set) array $lockedIds = [];

    private int $sequence = 0;

    public function __construct(private readonly BusinessContext $context) {}

    /** Generate a predictable identity. */
    public function nextId(): DecantPackPlanId
    {
        return new DecantPackPlanId(sprintf('0192f0c4-0000-7000-9000-%012d', ++$this->sequence));
    }

    public function save(DecantPackPlan $plan): void
    {
        $this->plans[$plan->id->value] = $plan;
    }

    /** Find an offer of the business of the operation and record the ones read to be changed, which the adapter locks. */
    public function find(DecantPackPlanId $id, bool $forUpdate = false): ?DecantPackPlan
    {
        if ($forUpdate) {
            $this->lockedIds[] = $id->value;
        }

        $plan = $this->plans[$id->value] ?? null;

        return $plan !== null && $plan->businessId->equals($this->context->current()) ? $plan : null;
    }

    public function all(): array
    {
        $plans = array_values(array_filter($this->plans, fn (DecantPackPlan $plan): bool => $plan->businessId->equals($this->context->current())));
        usort($plans, fn (DecantPackPlan $a, DecantPackPlan $b): int => [$a->name->value, $a->id->value] <=> [$b->name->value, $b->id->value]);

        return $plans;
    }

    /** Count the stored offers of every business. */
    public function count(): int
    {
        return count($this->plans);
    }
}
