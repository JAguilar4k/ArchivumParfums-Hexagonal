<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;

final readonly class BusinessTransaction
{
    public const string SETTING = 'app.business_id';

    /**
     * Run the operation in a database transaction that only reaches the rows of one business; null reaches none.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function run(?string $businessId, Closure $operation): mixed
    {
        return DB::transaction(function () use ($businessId, $operation): mixed {
            $this->scope($businessId);

            return $operation();
        });
    }

    /** Set the business of the open transaction; the value disappears when the transaction ends. */
    public function scope(?string $businessId): void
    {
        DB::statement('select set_config(?, ?, true)', [self::SETTING, $businessId ?? '']);
    }
}
