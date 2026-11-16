<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Str;
use Src\Decants\Packs\Domain\Contracts\DecantPackPlanRepository;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantPackPlanNotFoundException;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackPlanId;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class EloquentDecantPackPlanRepository implements DecantPackPlanRepository
{
    /** Receive the business of the operation, which every query filters by besides row-level security. */
    public function __construct(private BusinessContext $context) {}

    /** Generate a time-ordered UUID for a new offer. */
    public function nextId(): DecantPackPlanId
    {
        return new DecantPackPlanId((string) Str::uuid7());
    }

    /** Insert or update the offer, only in the business of the operation. */
    public function save(DecantPackPlan $plan): void
    {
        if (! $plan->businessId->equals($this->context->current())) {
            throw new DecantPackPlanNotFoundException('packs.plan_not_found');
        }

        DecantPackPlanModel::query()->updateOrCreate(
            ['id' => $plan->id->value, 'business_id' => $plan->businessId->value],
            [
                'name' => $plan->name->value,
                'decants_count' => $plan->decants->value,
                'ml_per_decant' => $plan->mlPerDecant->value,
                'price' => $plan->price->amount,
                'validity_days' => $plan->validity->days,
                'max_price_per_ml' => $plan->maxPricePerMl?->amount,
                'is_active' => $plan->isActive,
            ],
        );
    }

    public function find(DecantPackPlanId $id, bool $forUpdate = false): ?DecantPackPlan
    {
        $query = DecantPackPlanModel::query()->where('business_id', $this->context->current()->value)->whereKey($id->value);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function all(): array
    {
        return array_values(DecantPackPlanModel::query()
            ->where('business_id', $this->context->current()->value)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map($this->toDomain(...))
            ->all());
    }

    /** Rebuild the aggregate from its stored state; the stored ml stay valid even if the store stopped selling that size. */
    private function toDomain(DecantPackPlanModel $row): DecantPackPlan
    {
        return DecantPackPlan::reconstitute(
            new DecantPackPlanId($row->id),
            new BusinessId($row->business_id),
            new PlanName($row->name),
            new DecantCount($row->decants_count),
            new MlPerDecant($row->ml_per_decant),
            new PackPrice($row->price),
            new ValidityDays($row->validity_days),
            $row->max_price_per_ml === null ? null : new MaxPricePerMl($row->max_price_per_ml),
            $row->is_active,
        );
    }
}
