<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Payments\Payments\Domain\Contracts\RefundRepository;
use Src\Payments\Payments\Domain\Entities\Refund;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\RefundId;
use Src\Shared\Domain\Contracts\BusinessContext;
use stdClass;

final readonly class DatabaseRefundRepository implements RefundRepository
{
    public function __construct(private BusinessContext $business) {}

    public function nextId(): RefundId
    {
        return new RefundId((string) Str::uuid7());
    }

    public function find(RefundId $id, bool $forUpdate = false): ?Refund
    {
        $query = $this->refunds()->where('id', $id->value);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row === null ? null : $this->restore($row);
    }

    public function findByReference(string $gateway, string $reference): ?Refund
    {
        $row = $this->refunds()->where('provider_reference', $reference)
            ->whereExists(fn (Builder $payments) => $payments->from('payments')->whereColumn('payments.id', 'refunds.payment_id')
                ->whereColumn('payments.business_id', 'refunds.business_id')->where('payments.gateway', $gateway))
            ->orderBy('id')->first();

        return $row === null ? null : $this->restore($row);
    }

    public function pendingAmountOf(PaymentId $paymentId): int
    {
        return (int) $this->refunds()->where('payment_id', $paymentId->value)->where('status', 'pending')->sum('amount');
    }

    public function inFlightAmountOf(PaymentId $paymentId): int
    {
        return (int) $this->refunds()->where('payment_id', $paymentId->value)->where('status', 'pending')->whereNotNull('attempted_at')->sum('amount');
    }

    public function ofOrder(string $orderId): array
    {
        return array_values(DB::table('refunds as r')
            ->join('payments as p', fn (JoinClause $join) => $join->on('p.id', '=', 'r.payment_id')->on('p.business_id', '=', 'r.business_id'))
            ->where('r.business_id', $this->business->current()->value)->where('p.order_id', $orderId)
            ->orderBy('r.id')->select('r.*')->get()
            ->map(fn (stdClass $row): Refund => $this->restore($row))->all());
    }

    public function add(Refund $refund): void
    {
        $this->guard($refund);
        DB::table('refunds')->insert([
            'id' => $refund->id,
            'business_id' => $refund->businessId,
            'payment_id' => $refund->paymentId,
            'order_line_id' => $refund->orderLineId,
            'amount' => $refund->amount,
            'status' => $refund->status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function save(Refund $refund): void
    {
        $this->guard($refund);
        $this->refunds()->where('id', $refund->id)->update($this->changes($refund));
        $this->keepAttempt($refund);
    }

    public function saveIfPending(Refund $refund): bool
    {
        $this->guard($refund);
        if ($this->refunds()->where('id', $refund->id)->where('status', 'pending')->update($this->changes($refund)) !== 1) {
            return false;
        }
        $this->keepAttempt($refund);

        return true;
    }

    /** The refunds of the current business. */
    private function refunds(): Builder
    {
        return DB::table('refunds')->where('business_id', $this->business->current()->value);
    }

    /** @return array<string, mixed> */
    private function changes(Refund $refund): array
    {
        return [
            'status' => $refund->status,
            'provider_reference' => $refund->providerReference,
            'charged_amount' => $refund->chargedAmount,
            'updated_at' => now(),
        ];
    }

    /** The moment the request left is set once, when the refund starts, and kept as it was by every later change. */
    private function keepAttempt(Refund $refund): void
    {
        if ($refund->attempted) {
            $this->refunds()->where('id', $refund->id)->whereNull('attempted_at')->update(['attempted_at' => now()]);
        }
    }

    private function guard(Refund $refund): void
    {
        if ($refund->businessId !== $this->business->current()->value) {
            throw new PaymentNotFoundException('payments.not_found');
        }
    }

    private function restore(stdClass $row): Refund
    {
        return Refund::reconstitute(
            (string) $row->id,
            (string) $row->business_id,
            (string) $row->payment_id,
            (int) $row->amount,
            is_string($row->order_line_id) ? $row->order_line_id : null,
            (string) $row->status,
            is_string($row->provider_reference) ? $row->provider_reference : null,
            $row->attempted_at !== null,
            $row->charged_amount === null ? null : (int) $row->charged_amount,
        );
    }
}
