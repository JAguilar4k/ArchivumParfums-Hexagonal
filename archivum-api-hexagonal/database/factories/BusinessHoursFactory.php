<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models\BusinessHoursModel;

/**
 * @extends Factory<BusinessHoursModel>
 */
final class BusinessHoursFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = BusinessHoursModel::class;

    /**
     * Default state of a fictitious range of hours in which a business attends, on any day of the week.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'weekday' => fake()->numberBetween(1, 7),
            'opens_at' => '08:00',
            'closes_at' => '17:00',
        ];
    }
}
