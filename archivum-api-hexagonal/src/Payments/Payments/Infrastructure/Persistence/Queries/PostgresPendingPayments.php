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
     *
     * Besides the pending payments, it reads the expired payments of expired orders that the expiry closed (#158)
     * between Payment::MINUTES_FOR_THE_NOTICE minutes and Payment::HOURS_FOR_A_LATE_CHARGE hours ago, when the payment
     * last changed: the provider may still have charged them, and then their refund is requested (#192).
     */
    public function dueAt(DateTimeImmutable $now, int $limit): array
    {
        $waitedSince = $now->modify('-'.Payment::MINUTES_FOR_THE_NOTICE.' minutes')->format(DATE_ATOM);
        $lateSince = $now->modify('-'.Payment::HOURS_FOR_A_LATE_CHARGE.' hours')->format(DATE_ATOM);
        $payments = [];

        foreach (DB::table('businesses')->orderBy('id')->pluck('id') as $businessId) {
            $businessId = (string) $businessId;
            $remaining = $limit - count($payments);
            if ($remaining <= 0) {
                break;
            }

            /** @var list<object{id: string}> $rows */
            $rows = $this->transaction->run($businessId, fn (): array => DB::select(
                "select p.id
                   from payments p
                   join orders o on o.id = p.order_id and o.business_id = p.business_id
                  where p.business_id = ?
                    and p.provider_reference is not null
                    and ((p.status = 'pending' and p.created_at <= ?)
                      or (p.status = 'expired' and o.status = 'expired' and p.updated_at <= ? and p.updated_at > ?))
                  order by p.created_at
                  limit ?",
                [$businessId, $waitedSince, $waitedSince, $lateSince, $remaining],
            ));

            foreach ($rows as $row) {
                $payments[] = new PaymentReference($row->id, $businessId);
            }
        }

        return $payments;
    }
}
