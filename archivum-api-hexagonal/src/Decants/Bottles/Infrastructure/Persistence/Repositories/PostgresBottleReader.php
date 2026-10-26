<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Decants\Bottles\Application\Contracts\BottleReader;
use Src\Decants\Bottles\Application\DTOs\BottlePage;
use Src\Decants\Bottles\Application\DTOs\BottleView;
use Src\Decants\Bottles\Application\DTOs\MovementPage;
use Src\Decants\Bottles\Application\DTOs\MovementView;
use Src\Decants\Bottles\Domain\Exceptions\BottleNotFoundException;
use Src\Shared\Domain\Contracts\BusinessContext;
use stdClass;

final readonly class PostgresBottleReader implements BottleReader
{
    public function __construct(private BusinessContext $context) {}

    public function page(int $page, int $perPage): BottlePage
    {
        $query = $this->bottles();
        $total = $query->count();
        $rows = $query->orderByDesc('b.opened_at')->orderByDesc('b.id')->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return new BottlePage(array_values($rows->map($this->toBottle(...))->all()), $total, $page, $perPage);
    }

    public function find(string $id): ?BottleView
    {
        $row = $this->bottles()->where('b.id', $id)->first();

        return $row === null ? null : $this->toBottle($row);
    }

    public function presentationMl(string $variantId): ?int
    {
        $ml = DB::table('perfume_variants')->where('business_id', $this->context->current()->value)->where('id', $variantId)->value('size_ml');

        return $ml === null ? null : (int) $ml;
    }

    public function movements(string $bottleId, int $page, int $perPage): MovementPage
    {
        $query = $this->movementQuery()->where('m.decant_bottle_id', $bottleId);
        $total = $query->count();
        $rows = $query->orderByDesc('m.created_at')->orderByDesc('m.id')->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return new MovementPage(array_values($rows->map($this->toMovement(...))->all()), $total, $page, $perPage);
    }

    public function movement(string $id): MovementView
    {
        $row = $this->movementQuery()->where('m.id', $id)->first() ?? throw BottleNotFoundException::create();

        return $this->toMovement($row);
    }

    private function bottles(): Builder
    {
        return DB::table('decant_bottles as b')
            ->join('perfume_variants as v', fn ($join) => $join->on('v.id', '=', 'b.perfume_variant_id')->on('v.business_id', '=', 'b.business_id'))
            ->join('perfumes as p', fn ($join) => $join->on('p.id', '=', 'v.perfume_id')->on('p.business_id', '=', 'v.business_id'))
            ->where('b.business_id', $this->context->current()->value)
            ->select(['b.*', 'v.size_ml as variant_ml', 'p.id as perfume_id', 'p.brand', 'p.name as perfume_name']);
    }

    private function movementQuery(): Builder
    {
        return DB::table('decant_movements as m')->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.business_id', $this->context->current()->value)
            ->select(['m.*', 'u.name as author']);
    }

    private function toBottle(stdClass $row): BottleView
    {
        return new BottleView((string) $row->id, (string) $row->perfume_variant_id, (string) $row->perfume_id, (string) $row->brand,
            (string) $row->perfume_name, (int) $row->variant_ml, (int) $row->total_ml, (int) $row->remaining_ml, (int) $row->reserved_ml,
            (string) $row->status, (string) $row->opened_at, $row->notes === null ? null : (string) $row->notes);
    }

    private function toMovement(stdClass $row): MovementView
    {
        return new MovementView((string) $row->id, (string) $row->type, (int) $row->ml, $row->reason === null ? null : (string) $row->reason,
            (string) $row->created_at, $row->author === null ? null : (string) $row->author, $row->order_line_id === null ? null : (string) $row->order_line_id);
    }
}
