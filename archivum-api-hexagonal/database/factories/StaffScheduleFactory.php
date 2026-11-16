<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Scheduling\StaffSchedules\Infrastructure\Persistence\Models\StaffScheduleModel;

/**
 * @extends Factory<StaffScheduleModel>
 */
final class StaffScheduleFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = StaffScheduleModel::class;

    /**
     * Default state of a fictitious range of hours of a member of the same business who attends appointments.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'member_id' => fn (array $attributes): string => MemberModel::factory()->create(['business_id' => $attributes['business_id'], 'attends_appointments' => true])->id,
            'weekday' => fake()->numberBetween(1, 7),
            'starts_at' => '08:00',
            'ends_at' => '12:00',
        ];
    }
}
