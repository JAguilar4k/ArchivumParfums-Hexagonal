<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\BlockOrigin;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Persistence\Models\ScheduleBlockModel;

/**
 * @extends Factory<ScheduleBlockModel>
 */
final class ScheduleBlockFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = ScheduleBlockModel::class;

    /**
     * Default state of a fictitious block of one hour, created by hand, that covers the whole business.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'member_id' => null,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'reason' => fake()->sentence(),
            'source' => BlockOrigin::MANUAL->value,
        ];
    }
}
