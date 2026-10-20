<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;

/**
 * @extends Factory<CustomerModel>
 */
final class CustomerFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = CustomerModel::class;

    /**
     * Default state of a fictitious customer of one business, with a test phone.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'user_id' => UserModel::factory(),
            'phone' => fake()->unique()->numerify('+5068888####'),
        ];
    }
}
