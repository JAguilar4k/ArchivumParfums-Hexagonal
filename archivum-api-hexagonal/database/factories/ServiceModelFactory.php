<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;

/**
 * @extends Factory<ServiceModel>
 */
class ServiceModelFactory extends Factory
{
    protected $model = ServiceModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'duration_minutes' => $this->faker->numberBetween(15, 60),
            'price' => $this->faker->numberBetween(1000, 50000),
            'buffer_minutes' => 0,
            'max_perfumes' => 5,
            'is_active' => true,
        ];
    }
}
