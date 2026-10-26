<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Src\Payments\Payments\Domain\Contracts\RefundRepository;
use Src\Payments\Payments\Domain\Entities\Refund;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class DatabaseRefundRepository implements RefundRepository
{
    public function __construct(private BusinessContext $business) {}

    /** @return list<Refund> */
    public function pendingForOrder(string $orderId): array
    {
        $business = $this->business->current()->value;
        if (DB::table('orders')->where('business_id', $business)->where('id', $orderId)->lockForUpdate()->first() === null) {
            throw new PaymentNotFoundException('payments.not_found');
        }

        $refunds = DB::table('refunds as r')->join('payments as p', fn ($join) => $join->on('p.id', '=', 'r.payment_id')->on('p.business_id', '=', 'r.business_id'))
            ->where('r.business_id', $business)->where('p.order_id', $orderId)->where('r.status', 'pending')
            ->orderBy('r.id')->select('r.*')->lockForUpdate()->get()->map(fn (object $row): Refund => Refund::reconstitute((string) $row->id, (string) $row->business_id, (string) $row->payment_id, (int) $row->amount, is_string($row->order_line_id) ? $row->order_line_id : null, (string) $row->status, is_string($row->provider_reference) ? $row->provider_reference : null, $row->attempted_at !== null))->values()->all();

        return array_values($refunds);
    }

    public function save(Refund $refund): void
    {
        $business = $this->business->current()->value;
        if ($refund->businessId !== $business) {
            throw new PaymentNotFoundException('payments.not_found');
        }
        DB::table('refunds')->where('business_id', $business)->where('id', $refund->id)->update([
            'status' => $refund->status,
            'provider_reference' => $refund->providerReference,
            'attempted_at' => $refund->attempted ? now() : null,
            'updated_at' => now(),
        ]);
    }
}
