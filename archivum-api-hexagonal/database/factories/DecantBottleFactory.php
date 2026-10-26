<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeVariantModel;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

/** @extends Factory<DecantBottleModel> */
final class DecantBottleFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = DecantBottleModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'perfume_variant_id' => function (array $attributes): string {
                $businessId = (string) $attributes['business_id'];

                return new BusinessTransaction()->run($businessId, function () use ($businessId): string {
                    $perfume = PerfumeModel::factory()->create(['business_id' => $businessId]);

                    return (string) PerfumeVariantModel::query()->create([
                        'business_id' => $businessId, 'perfume_id' => $perfume->id, 'size_ml' => 100,
                        'sale_price' => 10000, 'sale_price_override' => 10000, 'is_active' => true,
                    ])->getKey();
                });
            },
            'total_ml' => 100,
            'remaining_ml' => 100,
            'reserved_ml' => 0,
            'status' => 'open',
            'opened_at' => '2026-10-24T10:00:00-06:00',
            'notes' => null,
        ];
    }

    public function forPresentation(string $businessId, string $variantId, int $capacity): static
    {
        return $this->state(['business_id' => $businessId, 'perfume_variant_id' => $variantId, 'total_ml' => $capacity, 'remaining_ml' => $capacity]);
    }

    public function withRemainingMl(int $ml): static
    {
        return $this->state(['remaining_ml' => $ml, 'status' => $ml === 0 ? 'empty' : 'open']);
    }
}
