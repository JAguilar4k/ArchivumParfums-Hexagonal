<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Decants\Bottles\Application\UseCases\AdjustDecantBottle;
use Src\Decants\Bottles\Domain\Contracts\MlReservation;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('records the ml a bottle really had when a sale consumes it while it is adjusted', function () {
    $businessId = (string) Str::uuid7();
    $perfumeId = (string) Str::uuid7();
    $variantId = (string) Str::uuid7();
    $bottleId = (string) Str::uuid7();
    $owner = DB::connection('pgsql_admin');
    $userId = (string) $owner->table('users')->insertGetId(['name' => 'Dueña paralela', 'email' => "duena-{$businessId}@archivum.test", 'password' => 'hash', 'created_at' => now(), 'updated_at' => now()]);
    $owner->transaction(function () use ($owner, $businessId, $perfumeId, $variantId, $bottleId): void {
        $owner->statement("SELECT set_config('app.business_id', ?, true)", [$businessId]);
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Perfumería paralela', 'slug' => 'parallel-'.$businessId, 'timezone' => 'America/Costa_Rica', 'settings' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $owner->table('perfumes')->insert(['id' => $perfumeId, 'business_id' => $businessId, 'brand' => 'Marca ficticia', 'name' => 'Perfume paralelo', 'created_at' => now(), 'updated_at' => now()]);
        $owner->table('perfume_variants')->insert(['id' => $variantId, 'business_id' => $businessId, 'perfume_id' => $perfumeId, 'size_ml' => 150, 'sale_price' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $owner->table('decant_bottles')->insert(['id' => $bottleId, 'business_id' => $businessId, 'perfume_variant_id' => $variantId, 'total_ml' => 150, 'remaining_ml' => 100, 'reserved_ml' => 5, 'status' => 'open', 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    });

    // The adjustment commits its entry from another process, and audit_logs refuses every change. Once the transaction
    // of the test rolls back and leaves no lock on the table, only its owner, with the trigger off for a moment,
    // empties it; the test database keeps no other committed rows.
    // @phpstan-ignore method.notFound (the proxy forwards the call to the running test case)
    $this->beforeApplicationDestroyed(fn () => DB::connection('pgsql_admin')->transaction(function (): void {
        $owner = DB::connection('pgsql_admin');
        $owner->statement('ALTER TABLE audit_logs DISABLE TRIGGER trg_audit_logs_immutable');
        $owner->statement('TRUNCATE audit_logs');
        $owner->statement('ALTER TABLE audit_logs ENABLE TRIGGER trg_audit_logs_immutable');
    }));

    inParallel(['businesses', 'perfumes', 'perfume_variants', 'decant_bottles', 'decant_movements', 'users'],
        // The sale consumes 5 ml first and keeps the row locked until it commits.
        static function () use ($businessId, $bottleId): string {
            return new BusinessTransaction()->run($businessId, function () use ($businessId, $bottleId): string {
                app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($businessId)));
                app(MlReservation::class)->consume($bottleId, 5);
                usleep(1_500_000);

                return 'consumed';
            });
        },
        // The adjustment starts while the sale holds the row: it can still read 100 ml, then waits for the lock.
        static function () use ($businessId, $bottleId, $userId): string {
            usleep(500_000);

            return new BusinessTransaction()->run($businessId, function () use ($businessId, $bottleId, $userId): string {
                app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($businessId)));
                app(AdjustDecantBottle::class)->execute($bottleId, 10, 'Recuento físico', $userId);

                return 'adjusted';
            });
        });

    $entry = app(BusinessTransaction::class)->run($businessId, fn (): ?stdClass => DB::table('audit_logs')->where('resource_id', $bottleId)->first());
    $remaining = app(BusinessTransaction::class)->run($businessId, fn (): mixed => DB::table('decant_bottles')->where('id', $bottleId)->value('remaining_ml'));

    expect($remaining)->toBe(105)
        ->and($entry)->not->toBeNull()
        ->and(json_decode((string) $entry?->before, true))->toEqual(['remaining_ml' => 95])
        ->and(json_decode((string) $entry?->after, true))->toEqual(['remaining_ml' => 105, 'reason' => 'Recuento físico']);
});
