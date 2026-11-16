<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Decants\Packs\Application\Contracts\DecantPackReader;
use Src\Decants\Packs\Application\DTOs\PurchasedPackView;
use Src\Decants\Packs\Application\DTOs\SoldPackPage;
use Src\Decants\Packs\Application\DTOs\SoldPackView;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Shared\Domain\Contracts\BusinessContext;
use stdClass;

/** Read the bought packs straight from their tables, without the domain, like the offers on sale. */
final readonly class PostgresDecantPackReader implements DecantPackReader
{
    /**
     * The status of a pack at a moment, as DecantPack::statusAt answers it: a pack that waits for its payment or is
     * active is expired once its deadline passed, even before the queue expires it (#159). The list of the panel
     * filters by this same expression, so a page never mixes the stored status with the current one.
     */
    private const string STATUS_AT = "CASE WHEN p.status IN ('pending_payment', 'active') AND p.expires_at <= ? THEN 'expired' ELSE p.status END";

    /** Receive the business of the operation, which every query filters by besides row-level security. */
    public function __construct(private BusinessContext $context) {}

    public function ofAccount(string $userId, DateTimeImmutable $at): array
    {
        $rows = $this->packs($at)
            ->join('businesses as b', 'b.id', '=', 'p.business_id')
            ->where('c.user_id', $userId)
            ->addSelect(['b.name as business_name', 'b.slug as business_slug', 'p.business_id'])
            ->orderByDesc('p.created_at')
            ->orderByDesc('p.id')
            ->get();

        return array_values($rows->map(fn (stdClass $row): PurchasedPackView => new PurchasedPackView(
            (string) $row->id, (string) $row->business_id, (string) $row->business_name, (string) $row->business_slug,
            (string) $row->plan_id, (string) $row->plan_name, (int) $row->total_decants, (int) $row->remaining_decants,
            (int) $row->ml_per_decant, (string) $row->status_at, $this->atom($row->expires_at), $this->atom($row->created_at),
        ))->all());
    }

    public function sold(?DecantPackStatus $status, DateTimeImmutable $at, int $page, int $perPage): SoldPackPage
    {
        $query = $this->packs($at)
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->addSelect('u.name as customer_name');

        if ($status !== null) {
            $query->whereRaw('('.self::STATUS_AT.') = ?', [$at->format(DATE_ATOM), $status->value]);
        }

        $total = $query->count();
        $rows = $query->orderByDesc('p.created_at')->orderByDesc('p.id')->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return new SoldPackPage(array_values($rows->map(fn (stdClass $row): SoldPackView => new SoldPackView(
            (string) $row->id, (string) $row->customer_name, (string) $row->plan_id, (string) $row->plan_name,
            (int) $row->total_decants, (int) $row->remaining_decants, (int) $row->ml_per_decant, (string) $row->status_at,
            $this->atom($row->expires_at),
        ))->all()), $total, $page, $perPage);
    }

    /** The packs of the business of the operation with their offer and their customer, and the status of each at that moment. */
    private function packs(DateTimeImmutable $at): Builder
    {
        return DB::table('decant_packs as p')
            ->join('decant_pack_plans as pl', fn ($join) => $join->on('pl.id', '=', 'p.decant_pack_plan_id')->on('pl.business_id', '=', 'p.business_id'))
            ->join('customers as c', fn ($join) => $join->on('c.id', '=', 'p.customer_id')->on('c.business_id', '=', 'p.business_id'))
            ->where('p.business_id', $this->context->current()->value)
            ->select(['p.id', 'p.total_decants', 'p.remaining_decants', 'p.ml_per_decant', 'p.expires_at', 'p.created_at', 'pl.id as plan_id', 'pl.name as plan_name'])
            ->selectRaw(self::STATUS_AT.' AS status_at', [$at->format(DATE_ATOM)]);
    }

    /** A timestamp as PostgreSQL returns it, in ISO 8601 with its offset. */
    private function atom(mixed $timestamp): string
    {
        return new DateTimeImmutable(is_string($timestamp) ? $timestamp : '')->format(DATE_ATOM);
    }
}
