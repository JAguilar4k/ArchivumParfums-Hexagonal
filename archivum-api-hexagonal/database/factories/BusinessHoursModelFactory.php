<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models\BusinessHoursModel;

/**
 * @extends Factory<BusinessHoursModel>
 */
class BusinessHoursModelFactory extends Factory
{
    protected $model = BusinessHoursModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'weekday' => $this->faker->numberBetween(1, 7),
            'opens_at' => '08:00',
            'closes_at' => '17:00',
        ];
    }
}
