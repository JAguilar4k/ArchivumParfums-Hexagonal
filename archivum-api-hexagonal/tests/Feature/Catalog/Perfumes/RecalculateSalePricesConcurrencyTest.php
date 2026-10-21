<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Catalog\Perfumes\Application\UseCases\RecalculateSalePrices;
use Src\Catalog\Perfumes\Domain\Contracts\VariantCost;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Shared\Domain\ValueObjects\Colones;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

it('never overwrites a manual price committed by another connection while the prices are recalculated', function () {
    Artisan::call('migrate:fresh', ['--database' => 'pgsql_admin', '--force' => true]);
    $admin = DB::connection('pgsql_admin');
    $now = now();
    $business = (string) $admin->table('businesses')->insertGetId([
        'name' => 'ArchivumParfums', 'slug' => 'concurrent-prices', 'location_text' => '',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $perfume = (string) Str::uuid7();
    $edited = (string) Str::uuid7();
    $untouched = (string) Str::uuid7();
    // The schema owner is also subject to row-level security, so each write names the business of its transaction.
    $asBusiness = fn (Closure $operation): mixed => $admin->transaction(function () use ($admin, $business, $operation): mixed {
        $admin->statement('select set_config(?, ?, true)', [BusinessTransaction::SETTING, $business]);

        return $operation();
    });

    try {
        $asBusiness(function () use ($admin, $business, $perfume, $edited, $untouched, $now): void {
            $admin->table('perfumes')->insert([
                'id' => $perfume, 'business_id' => $business, 'brand' => 'Tom Ford', 'name' => 'Oud Wood',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $admin->table('perfume_variants')->insert(array_map(fn (string $id, int $ml): array => [
                'id' => $id, 'business_id' => $business, 'perfume_id' => $perfume, 'size_ml' => $ml,
                'created_at' => $now, 'updated_at' => $now,
            ], [$edited, $untouched], [5, 10]));
        });

        // The recalculation has already read the presentation without a manual price when, on a second connection, the
        // owner fixes one by hand and commits; the calculated price must then not be written.
        app()->instance(VariantCost::class, new class($asBusiness, $admin, $edited) implements VariantCost
        {
            public function __construct(private readonly Closure $asBusiness, private readonly ConnectionInterface $admin, private readonly string $edited) {}

            public function costOf(PerfumeVariantId $variant): Colones
            {
                if ($variant->value === $this->edited) {
                    ($this->asBusiness)(fn (): int => $this->admin->table('perfume_variants')->where('id', $this->edited)
                        ->update(['sale_price' => 90000, 'sale_price_override' => 90000]));
                }

                return new Colones(100000);
            }
        });

        $changed = app(BusinessTransaction::class)->run(
            $business,
            fn (): int => app(RecalculateSalePrices::class)->execute($business, 7000),
        );

        $edit = $asBusiness(fn (): ?object => $admin->table('perfume_variants')->where('id', $edited)->first());
        $other = $asBusiness(fn (): ?object => $admin->table('perfume_variants')->where('id', $untouched)->first());

        expect($edit->sale_price)->toBe(90000)
            ->and($edit->sale_price_override)->toBe(90000)
            ->and($other->sale_price)->toBe(107000)
            ->and($changed)->toBe(1);
    } finally {
        $admin->table('businesses')->where('id', $business)->delete();
    }
});
