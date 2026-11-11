<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Persistence\Models\ScheduleBlockModel;

/**
 * @extends Factory<ScheduleBlockModel>
 */
class ScheduleBlockModelFactory extends Factory
{
    protected $model = ScheduleBlockModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => null,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'reason' => $this->faker->sentence(),
            'source' => 'manual',
        ];
    }
}
