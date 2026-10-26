<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;

/** @extends Factory<OrderModel> */
final class OrderFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = OrderModel::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['business_id' => BusinessModel::factory(), 'customer_id' => fn (array $attributes): string => CustomerModel::factory()->create(['business_id' => $attributes['business_id']])->id,
            'status' => 'pending_payment', 'subtotal' => 15000, 'due_now' => 7500, 'paid' => 0, 'balance' => 7500, 'deposit_percent' => 50, 'expires_at' => now()->addMinutes(30)];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed', 'paid' => 7500]);
    }

    public function ready(): static
    {
        return $this->state(['status' => 'ready', 'paid' => 15000, 'balance' => 0]);
    }
}
