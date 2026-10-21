<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;

/**
 * @extends Factory<PerfumeModel>
 */
final class PerfumeFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = PerfumeModel::class;

    /**
     * Default state of a fictitious active perfume with only its brand and name.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'brand' => fake()->company(),
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
