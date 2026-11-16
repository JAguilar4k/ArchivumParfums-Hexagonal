<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;

/**
 * @extends Factory<AppointmentModel>
 */
final class AppointmentModelFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = AppointmentModel::class;

    /**
     * Default state of a confirmed appointment of tomorrow at 10:00, with a 45 minute service and 15 minutes of margin.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::tomorrow()->setTime(10, 0);

        return [
            'business_id' => BusinessModel::factory(),
            'service_id' => fn (array $attributes): string => ServiceModel::factory()
                ->create(['business_id' => $attributes['business_id']])->id,
            'customer_id' => fn (array $attributes): string => CustomerModel::factory()
                ->create(['business_id' => $attributes['business_id']])->id,
            'member_id' => fn (array $attributes): string => MemberModel::factory()
                ->create(['business_id' => $attributes['business_id'], 'attends_appointments' => true])->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(45),
            'blocked_until' => $startsAt->copy()->addHour(),
            'status' => 'confirmed',
        ];
    }

    /** Make the appointment one the customer cancelled, which stops blocking the slot. */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'cancelled',
            'cancelled_at' => Carbon::parse($attributes['starts_at'])->subDay(),
            'cancel_reason' => 'La clienta avisó',
        ]);
    }
}
