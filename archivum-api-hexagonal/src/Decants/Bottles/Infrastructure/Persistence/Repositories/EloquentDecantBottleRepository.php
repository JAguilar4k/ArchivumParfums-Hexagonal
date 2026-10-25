<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Src\Decants\Bottles\Domain\Contracts\DecantBottleRepository;
use Src\Decants\Bottles\Domain\Entities\DecantBottle;
use Src\Decants\Bottles\Domain\Exceptions\BottleNotFoundException;
use Src\Decants\Bottles\Domain\ValueObjects\BottleStatus;
use Src\Decants\Bottles\Domain\ValueObjects\DecantBottleId;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class EloquentDecantBottleRepository implements DecantBottleRepository
{
    public function __construct(private BusinessContext $context) {}

    public function nextId(): DecantBottleId
    {
        return new DecantBottleId((string) Str::uuid7());
    }

    /** Insert once: an aggregate save must never overwrite concurrently reserved milliliters. */
    public function add(DecantBottle $bottle): void
    {
        if (! $bottle->businessId->equals($this->context->current())) {
            throw BottleNotFoundException::create();
        }

        DecantBottleModel::query()->create([
            'id' => $bottle->id->value,
            'business_id' => $bottle->businessId->value,
            'perfume_variant_id' => $bottle->perfumeVariantId,
            'total_ml' => $bottle->totalMl,
            'remaining_ml' => $bottle->remainingMl,
            'reserved_ml' => $bottle->reservedMl,
            'status' => $bottle->status->value,
            'opened_at' => $bottle->openedAt,
            'notes' => $bottle->notes,
        ]);
    }

    public function find(DecantBottleId $id): ?DecantBottle
    {
        $row = DecantBottleModel::query()->where('business_id', $this->context->current()->value)->find($id->value);

        return $row === null ? null : DecantBottle::reconstitute(
            new DecantBottleId($row->id), new BusinessId($row->business_id), $row->perfume_variant_id,
            $row->total_ml, $row->remaining_ml, $row->reserved_ml, BottleStatus::from($row->status),
            new DateTimeImmutable($row->opened_at->toIso8601String()), $row->notes,
        );
    }

    public function updateNotes(DecantBottleId $id, ?string $notes): void
    {
        if (DecantBottleModel::query()->where('business_id', $this->context->current()->value)->whereKey($id->value)->update(['notes' => $notes]) !== 1) {
            throw BottleNotFoundException::create();
        }
    }
}
