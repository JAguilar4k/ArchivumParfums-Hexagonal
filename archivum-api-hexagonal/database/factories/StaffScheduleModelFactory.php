<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Scheduling\StaffSchedules\Infrastructure\Persistence\Models\StaffScheduleModel;

/**
 * @extends Factory<StaffScheduleModel>
 */
class StaffScheduleModelFactory extends Factory
{
    protected $model = StaffScheduleModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'weekday' => $this->faker->numberBetween(1, 7),
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'member_id' => function (array $attributes) {
                return MemberModel::where('business_id', $attributes['business_id'])->firstOrFail()->id;
            },
        ];
    }
}
