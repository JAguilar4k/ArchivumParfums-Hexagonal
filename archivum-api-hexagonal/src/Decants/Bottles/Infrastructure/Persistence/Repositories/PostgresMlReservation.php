<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Persistence\Repositories;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Decants\Bottles\Domain\Contracts\MlReservation;
use Src\Decants\Bottles\Domain\Exceptions\InsufficientMlException;
use Src\Decants\Bottles\Domain\ValueObjects\MovementType;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantMovementModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class PostgresMlReservation implements MlReservation
{
    public function __construct(private BusinessContext $context) {}

    public function reserve(string $bottleId, int $ml, ?string $orderLineId = null, ?string $userId = null): void
    {
        $this->change($bottleId, $ml, MovementType::Reservation, $orderLineId, $userId);
    }

    public function release(string $bottleId, int $ml, ?string $orderLineId = null, ?string $userId = null): void
    {
        $this->change($bottleId, $ml, MovementType::Release, $orderLineId, $userId);
    }

    public function consume(string $bottleId, int $ml, ?string $orderLineId = null, ?string $userId = null): void
    {
        $this->change($bottleId, $ml, MovementType::Consumption, $orderLineId, $userId);
    }

    public function adjust(string $bottleId, int $ml, string $reason, string $userId): string
    {
        return $this->change($bottleId, $ml, MovementType::Adjustment, null, $userId, $reason);
    }

    /** PostgreSQL chooses the winner; the change and its audit row commit or roll back together. */
    private function change(string $bottleId, int $ml, MovementType $type, ?string $orderLineId, ?string $userId, ?string $reason = null): string
    {
        if ($ml === 0 || ($type !== MovementType::Adjustment && $ml < 0)) {
            throw new InvalidValueException('bottles.invalid_ml');
        }

        if ($type === MovementType::Adjustment && ($reason === null || trim($reason) === '')) {
            throw new InvalidValueException('bottles.reason_required');
        }

        [$set, $condition, $setBindings, $conditionBindings] = match ($type) {
            MovementType::Reservation => ['reserved_ml = reserved_ml + ?', "status = 'open' AND remaining_ml - reserved_ml >= ?", [$ml], [$ml]],
            MovementType::Release => ['reserved_ml = reserved_ml - ?', 'reserved_ml >= ?', [$ml], [$ml]],
            MovementType::Consumption => ["remaining_ml = remaining_ml - ?, reserved_ml = reserved_ml - ?, status = CASE WHEN remaining_ml = ? THEN 'empty' ELSE 'open' END", 'reserved_ml >= ?', [$ml, $ml, $ml], [$ml]],
            MovementType::Adjustment => ["remaining_ml = remaining_ml + ?, status = CASE WHEN remaining_ml + ? = 0 THEN 'empty' ELSE 'open' END", "status = 'open' AND remaining_ml::bigint + ? >= reserved_ml AND remaining_ml::bigint + ? <= total_ml", [$ml, $ml], [$ml, $ml]],
        };
        $businessId = $this->context->current()->value;
        $bindings = [...$setBindings, $bottleId, $businessId, ...$conditionBindings];

        try {
            return DB::transaction(function () use ($set, $condition, $bindings, $businessId, $bottleId, $type, $ml, $orderLineId, $userId, $reason): string {
                $changed = DB::update("UPDATE decant_bottles SET {$set}, updated_at = now() WHERE id = ? AND business_id = ? AND {$condition}", $bindings);

                if ($changed !== 1) {
                    throw InsufficientMlException::create();
                }

                return (string) DecantMovementModel::query()->create([
                    'business_id' => $businessId,
                    'decant_bottle_id' => $bottleId,
                    'type' => $type->value,
                    'ml' => $ml,
                    'order_line_id' => $orderLineId,
                    'user_id' => $userId,
                    'reason' => $reason,
                    'created_at' => now(),
                ])->getKey();
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23514') {
                throw InsufficientMlException::create();
            }

            throw $exception;
        }
    }
}
