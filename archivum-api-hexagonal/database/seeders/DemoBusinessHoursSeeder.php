<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\SeededBusinessContext;
use Illuminate\Database\Seeder;
use Src\Scheduling\BusinessHours\Application\DTOs\BusinessHoursSlot;
use Src\Scheduling\BusinessHours\Application\DTOs\ReplaceBusinessHoursCommand;
use Src\Scheduling\BusinessHours\Application\UseCases\ReplaceBusinessHours;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\EloquentBusinessHoursRepository;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models\BusinessHoursModel;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Persistence\DatabaseTransactionRunner;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final class DemoBusinessHoursSeeder extends Seeder
{
    /**
     * Opening hours of each demo business: Tuesday and Thursday from 17:00 to 20:00 and Saturday from 9:00 to 13:00,
     * the example the specification assumes.
     *
     * @var array<string, list<array{int, string, string}>>
     */
    private const array HOURS = [
        BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID => [[2, '17:00', '20:00'], [4, '17:00', '20:00'], [6, '09:00', '13:00']],
        BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID => [[2, '17:00', '20:00'], [4, '17:00', '20:00'], [6, '09:00', '13:00']],
    ];

    /**
     * Fill the opening hours of each demo business inside the transaction of that business and with the application
     * role, so the row-level policy accepts every row; repeating the seed keeps the hours the owner edited.
     */
    public function run(): void
    {
        foreach (self::HOURS as $businessId => $tramos) {
            new BusinessTransaction()->run(
                $businessId,
                function () use ($businessId, $tramos): void {
                    if (BusinessHoursModel::query()->where('business_id', $businessId)->exists()) {
                        return;
                    }

                    $context = new SeededBusinessContext(new BusinessId($businessId));
                    $replaceHours = new ReplaceBusinessHours(
                        new EloquentBusinessHoursRepository($context),
                        $context,
                        new DatabaseTransactionRunner,
                    );

                    $slots = array_map(
                        fn (array $tramo): BusinessHoursSlot => new BusinessHoursSlot($tramo[0], $tramo[1], $tramo[2]),
                        $tramos,
                    );

                    $replaceHours->execute(new ReplaceBusinessHoursCommand($slots));
                },
            );
        }
    }
}
