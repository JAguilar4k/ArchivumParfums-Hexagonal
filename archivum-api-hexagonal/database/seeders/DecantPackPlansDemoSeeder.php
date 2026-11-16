<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\SeededBusinessContext;
use Illuminate\Database\Seeder;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Decants\Packs\Application\DTOs\CreateDecantPackPlanCommand;
use Src\Decants\Packs\Application\UseCases\CreateDecantPackPlan;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\EloquentDecantPackPlanRepository;
use Src\Decants\Packs\Infrastructure\Settings\BusinessSettingsDecantSizes;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Persistence\DatabaseTransactionRunner;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final class DecantPackPlansDemoSeeder extends Seeder
{
    /** Name of the initial offer of each demo business. */
    public const string NAME = 'Descubrimiento';

    /**
     * Seed the initial offer of R32 in each demo business: 5 decants of 5 ml for 180 days, an assumption of the
     * specification, for ₡40.000 with a cap of ₡2.500 per ml (precisions of 08/11/2026). With that cap, 3 of the
     * presentations of each demo business fit, and the most expensive perfumes stay out. It is not repeated when the
     * seeder runs again.
     */
    public function run(): void
    {
        foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID] as $businessId) {
            new BusinessTransaction()->run($businessId, function () use ($businessId): void {
                if (DecantPackPlanModel::query()->where('business_id', $businessId)->where('name', self::NAME)->exists()) {
                    return;
                }

                $context = new SeededBusinessContext(new BusinessId($businessId));
                $createPlan = new CreateDecantPackPlan(
                    new EloquentDecantPackPlanRepository($context),
                    new BusinessSettingsDecantSizes($context, app(BusinessRepository::class)),
                    $context,
                    new DatabaseTransactionRunner,
                );

                $createPlan->execute(new CreateDecantPackPlanCommand(self::NAME, 5, 5, 40000, 180, 2500, true));
            });
        }
    }
}
