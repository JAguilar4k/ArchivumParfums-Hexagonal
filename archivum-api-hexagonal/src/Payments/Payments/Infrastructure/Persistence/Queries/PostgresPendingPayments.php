<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Persistence\Queries;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Payments\Payments\Application\Contracts\PendingPayments;
use Src\Payments\Payments\Application\DTOs\PaymentReference;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final readonly class PostgresPendingPayments implements PendingPayments
{
    /** Receive the transaction that pins one business for each reading. */
    public function __construct(private BusinessTransaction $transaction) {}

    /**
     * The application role cannot read the payments of every business at once: row security shows it only those of the
     * business pinned in the transaction. So it walks the businesses, which have no row policy, and reads the pending
     * payments of each one in a transaction of its own. It only reads, it never settles. The maintenance role of #157
     * will read them in one query.
     */
    public function dueAt(DateTimeImmutable $now, int $limit): array
    {
        $waitedSince = $now->modify('-'.Payment::MINUTES_FOR_THE_NOTICE.' minutes')->format(DATE_ATOM);
        $payments = [];

        foreach (DB::table('businesses')->orderBy('id')->pluck('id') as $businessId) {
            $businessId = (string) $businessId;
            $remaining = $limit - count($payments);
            if ($remaining <= 0) {
                break;
            }

            /** @var list<object{id: string}> $rows */
            $rows = $this->transaction->run($businessId, fn (): array => DB::select(
                "select id
                   from payments
                  where business_id = ?
                    and status = 'pending'
                    and provider_reference is not null
                    and created_at <= ?
                  order by created_at
                  limit ?",
                [$businessId, $waitedSince, $remaining],
            ));

            foreach ($rows as $row) {
                $payments[] = new PaymentReference($row->id, $businessId);
            }
        }

        return $payments;
    }
}
