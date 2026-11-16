<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;

/**
 * @extends Factory<ServiceModel>
 */
final class ServiceFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = ServiceModel::class;

    /**
     * Default state of a fictitious active service that a business offers to book.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'name' => fake()->words(3, true),
            'duration_minutes' => fake()->numberBetween(15, 60),
            'price' => fake()->numberBetween(1000, 50000),
            'buffer_minutes' => 0,
            'max_perfumes' => 5,
            'is_active' => true,
        ];
    }
}
