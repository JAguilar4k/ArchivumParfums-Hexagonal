<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Decants\Packs\Domain\Contracts\DecantPackBalance;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class PostgresDecantPackBalance implements DecantPackBalance
{
    public function __construct(private BusinessContext $context) {}

    /**
     * PostgreSQL chooses the winner: a second redemption of the same pack waits for the row this one changed and then
     * checks the condition again against the decants left. The CHECK constraints of #143 refuse a negative balance
     * even on a path that skipped the condition.
     */
    public function take(DecantPackId $id, DateTimeImmutable $at): bool
    {
        return DB::update(<<<'SQL'
            UPDATE decant_packs
            SET remaining_decants = remaining_decants - 1,
                status = CASE WHEN remaining_decants = 1 THEN 'exhausted' ELSE status END,
                updated_at = now()
            WHERE id = ? AND business_id = ? AND status = 'active' AND expires_at > ? AND remaining_decants > 0
            SQL, [$id->value, $this->context->current()->value, $at->format(DATE_ATOM)]) === 1;
    }
}
