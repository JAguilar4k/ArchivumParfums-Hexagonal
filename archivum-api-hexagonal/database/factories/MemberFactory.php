<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;

/**
 * @extends Factory<MemberModel>
 */
final class MemberFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = MemberModel::class;

    /**
     * Default state of a fictitious member of the staff who does not attend appointments.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'user_id' => UserModel::factory(),
            'role' => MemberRole::Staff->value,
            'attends_appointments' => false,
        ];
    }

    /** Make the member an owner of the business. */
    public function owner(): static
    {
        return $this->state(['role' => MemberRole::Owner->value]);
    }

    /** Make the member staff of the business, without the permissions of an owner. */
    public function staff(): static
    {
        return $this->state(['role' => MemberRole::Staff->value]);
    }
}
