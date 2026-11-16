<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackModel;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;

/** @extends Factory<DecantPackModel> */
final class DecantPackFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = DecantPackModel::class;

    /**
     * An active pack of 5 decants of 5 ml, bought from an offer and by a customer of its own business.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'customer_id' => fn (array $attributes): string => CustomerModel::factory()->create(['business_id' => $attributes['business_id']])->id,
            'decant_pack_plan_id' => fn (array $attributes): string => DecantPackPlanModel::factory()->create(['business_id' => $attributes['business_id']])->id,
            'total_decants' => 5,
            'remaining_decants' => 5,
            'ml_per_decant' => 5,
            'max_price_per_ml' => 2500,
            'status' => 'active',
            'expires_at' => now()->addDays(180),
        ];
    }

    /** A pack that waits for its payment, with the deadline of the payment as its expiry. */
    public function pendingPayment(): static
    {
        return $this->state(['status' => 'pending_payment', 'remaining_decants' => 5, 'expires_at' => now()->addMinutes(30)]);
    }

    /** A pack whose last decant was redeemed. */
    public function exhausted(): static
    {
        return $this->state(['status' => 'exhausted', 'remaining_decants' => 0]);
    }

    /** A pack the queue already expired with decants left. */
    public function expired(): static
    {
        return $this->state(['status' => 'expired', 'remaining_decants' => 3, 'expires_at' => now()->subDay()]);
    }
}
