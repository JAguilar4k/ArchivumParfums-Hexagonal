<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\SeededBusinessContext;
use Illuminate\Database\Seeder;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Scheduling\StaffSchedules\Application\DTOs\ReplaceStaffScheduleCommand;
use Src\Scheduling\StaffSchedules\Application\DTOs\StaffScheduleSlot;
use Src\Scheduling\StaffSchedules\Application\UseCases\ReplaceStaffSchedule;
use Src\Scheduling\StaffSchedules\Infrastructure\Persistence\EloquentStaffScheduleRepository;
use Src\Scheduling\StaffSchedules\Infrastructure\Persistence\Models\StaffScheduleModel;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Persistence\DatabaseTransactionRunner;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final class DemoStaffSchedulesSeeder extends Seeder
{
    /**
     * Working hours of every demo person who attends appointments: Tuesday and Thursday from 17:00 to 20:00 and Saturday
     * from 9:00 to 13:00, the same example as the store, so the availability of #61 has slots from the first delivery.
     *
     * @var list<array{int, string, string}>
     */
    private const array TRAMOS = [[2, '17:00', '20:00'], [4, '17:00', '20:00'], [6, '09:00', '13:00']];

    /**
     * Fill the hours of each person inside the transaction of her business and with the application role, so the
     * row-level policy accepts every row; a person who already has hours keeps them.
     */
    public function run(): void
    {
        foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID] as $businessId) {
            new BusinessTransaction()->run(
                $businessId,
                function () use ($businessId): void {
                    $members = MemberModel::query()
                        ->where('business_id', $businessId)
                        ->where('attends_appointments', true)
                        ->pluck('id');

                    foreach ($members as $memberId) {
                        if (StaffScheduleModel::query()->where('business_id', $businessId)->where('member_id', $memberId)->exists()) {
                            continue;
                        }

                        $context = new SeededBusinessContext(new BusinessId($businessId));
                        $replaceSchedule = new ReplaceStaffSchedule(
                            new EloquentStaffScheduleRepository($context),
                            $context,
                            new DatabaseTransactionRunner,
                        );

                        $slots = array_map(
                            fn (array $tramo): StaffScheduleSlot => new StaffScheduleSlot($tramo[0], $tramo[1], $tramo[2]),
                            self::TRAMOS,
                        );

                        $replaceSchedule->execute(new ReplaceStaffScheduleCommand((string) $memberId, $slots));
                    }
                },
            );
        }
    }
}
