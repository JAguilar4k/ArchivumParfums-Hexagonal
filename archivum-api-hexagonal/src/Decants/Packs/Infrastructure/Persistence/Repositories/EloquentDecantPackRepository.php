<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Persistence\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\Exceptions\DecantPackNotFoundException;
use Src\Decants\Packs\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackPlanId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Domain\ValueObjects\IdempotencyKey;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackModel;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackRedemptionModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class EloquentDecantPackRepository implements DecantPackRepository
{
    /** Receive the business of the operation, which every query filters by besides row-level security. */
    public function __construct(private BusinessContext $context) {}

    /** Generate a time-ordered UUID for a new pack. */
    public function nextId(): DecantPackId
    {
        return new DecantPackId((string) Str::uuid7());
    }

    /**
     * Insert the pack once, in a savepoint of its own: the unique key of the customer and the key turns a second pack
     * with the same key, also one inserted at the same time by another request, into a domain exception.
     */
    public function add(DecantPack $pack): void
    {
        $this->assertOfTheBusiness($pack);

        try {
            DB::transaction(fn () => DecantPackModel::query()->create([
                'id' => $pack->id->value,
                'business_id' => $pack->businessId->value,
                'customer_id' => $pack->customerId,
                'decant_pack_plan_id' => $pack->planId->value,
                'idempotency_key' => $pack->idempotencyKey?->value,
                ...$this->stateOf($pack),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw IdempotencyKeyReusedException::create();
        }
    }

    public function find(DecantPackId $id, bool $forUpdate = false): ?DecantPack
    {
        $query = DecantPackModel::query()->where('business_id', $this->context->current()->value)->whereKey($id->value);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function findByIdempotencyKey(string $customerId, IdempotencyKey $key): ?DecantPack
    {
        $row = DecantPackModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('customer_id', $customerId)
            ->where('idempotency_key', $key->value)
            ->first();

        return $row === null ? null : $this->toDomain($row);
    }

    /** Change only what a pack changes after its purchase: its status, its decants left and its expiry. */
    public function save(DecantPack $pack): void
    {
        $this->assertOfTheBusiness($pack);

        DecantPackModel::query()
            ->where('business_id', $pack->businessId->value)
            ->whereKey($pack->id->value)
            ->update($this->stateOf($pack));
    }

    /**
     * Besides the lock of the row, UPDATE ... WHERE status = the one it was read with: the database lets only one
     * operation take the pack out of that status, such as its expiry and the confirmation of its payment (#159).
     */
    public function saveIfStatus(DecantPack $pack, DecantPackStatus $expected): bool
    {
        $this->assertOfTheBusiness($pack);

        return DecantPackModel::query()
            ->where('business_id', $pack->businessId->value)
            ->whereKey($pack->id->value)
            ->where('status', $expected->value)
            ->update($this->stateOf($pack)) === 1;
    }

    /** The unique key of the business and the line keeps one redemption per line of an order. */
    public function addRedemption(DecantPackId $packId, string $orderLineId): void
    {
        DecantPackRedemptionModel::query()->create([
            'business_id' => $this->context->current()->value,
            'decant_pack_id' => $packId->value,
            'order_line_id' => $orderLineId,
        ]);
    }

    public function removeRedemption(DecantPackId $packId, string $orderLineId): bool
    {
        return DecantPackRedemptionModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('decant_pack_id', $packId->value)
            ->where('order_line_id', $orderLineId)
            ->delete() === 1;
    }

    /** A pack of another business is not one of this one. */
    private function assertOfTheBusiness(DecantPack $pack): void
    {
        if (! $pack->businessId->equals($this->context->current())) {
            throw new DecantPackNotFoundException('packs.pack_not_found');
        }
    }

    /** @return array<string, mixed> */
    private function stateOf(DecantPack $pack): array
    {
        return [
            'total_decants' => $pack->totalDecants,
            'remaining_decants' => $pack->remainingDecants,
            'ml_per_decant' => $pack->mlPerDecant,
            'max_price_per_ml' => $pack->maxPricePerMl?->amount,
            'status' => $pack->status->value,
            'expires_at' => $pack->expiresAt,
        ];
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(DecantPackModel $row): DecantPack
    {
        return DecantPack::reconstitute(
            new DecantPackId($row->id),
            new BusinessId($row->business_id),
            $row->customer_id,
            new DecantPackPlanId($row->decant_pack_plan_id),
            $row->total_decants,
            $row->remaining_decants,
            $row->ml_per_decant,
            $row->max_price_per_ml === null ? null : new MaxPricePerMl($row->max_price_per_ml),
            DecantPackStatus::from($row->status),
            $row->expires_at,
            $row->idempotency_key === null ? null : new IdempotencyKey($row->idempotency_key),
        );
    }
}
