<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;

/** @extends Factory<DecantPackPlanModel> */
final class DecantPackPlanFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = DecantPackPlanModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'name' => 'Descubrimiento',
            'decants_count' => 5,
            'ml_per_decant' => 5,
            'price' => 40000,
            'validity_days' => 180,
            'max_price_per_ml' => 2500,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
