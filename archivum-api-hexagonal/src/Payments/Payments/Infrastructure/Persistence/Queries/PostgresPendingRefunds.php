<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Persistence\Queries;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Payments\Payments\Application\Contracts\PendingRefunds;
use Src\Payments\Payments\Application\DTOs\RefundReference;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final readonly class PostgresPendingRefunds implements PendingRefunds
{
    /** Receive the transaction that pins one business for each reading. */
    public function __construct(private BusinessTransaction $transaction) {}

    /**
     * Row security shows the application role only the refunds of the business pinned in the transaction, so, as
     * PostgresPendingPayments does, it walks the businesses and reads the pending refunds of each one in a transaction
     * of its own. Only a refund with the provider's reference can be asked about. It only reads, it never settles.
     */
    public function dueAt(DateTimeImmutable $now, int $limit): array
    {
        $waitedSince = $now->modify('-'.Payment::MINUTES_FOR_THE_NOTICE.' minutes')->format(DATE_ATOM);
        $refunds = [];

        foreach (DB::table('businesses')->orderBy('id')->pluck('id') as $businessId) {
            $businessId = (string) $businessId;
            $remaining = $limit - count($refunds);
            if ($remaining <= 0) {
                break;
            }

            /** @var list<object{id: string}> $rows */
            $rows = $this->transaction->run($businessId, fn (): array => DB::select(
                "select id
                   from refunds
                  where business_id = ?
                    and status = 'pending'
                    and provider_reference is not null
                    and attempted_at <= ?
                  order by attempted_at
                  limit ?",
                [$businessId, $waitedSince, $remaining],
            ));

            foreach ($rows as $row) {
                $refunds[] = new RefundReference($row->id, $businessId);
            }
        }

        return $refunds;
    }
}
