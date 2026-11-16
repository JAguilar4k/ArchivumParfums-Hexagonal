<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models\BusinessHoursModel;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Scheduling\StaffSchedules\Infrastructure\Persistence\Models\StaffScheduleModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\patchJson;

uses(RefreshDatabaseAsOwner::class);

/** Pin the transaction of the test to the business, as the middleware of the request does. */
function scopeRescheduleTo(string $businessId): void
{
    app(BusinessTransaction::class)->scope($businessId);
}

/** Time zone of the store the scenario belongs to. */
function rescheduleZone(): DateTimeZone
{
    return new DateTimeZone((string) config('app.timezone'));
}

/** Start of the local day the scenario moves appointments to, a week ahead. */
function rescheduleDay(): DateTimeImmutable
{
    return (new DateTimeImmutable('now', rescheduleZone()))->setTime(0, 0)->modify('+7 days');
}

/**
 * A store of the seeded staff, open from 09:00 to 13:00, with a 60 minute service and two perfumes, and one
 * appointment of its customer at 09:00 of the day of the scenario.
 *
 * @param  array<string, mixed>  $settings
 * @return array{staffed: StaffedBusiness, service: ServiceModel, day: DateTimeImmutable, member: string, appointment: string, perfume: string}
 */
function rescheduleScenario(array $settings = []): array
{
    $staffed = StaffedBusiness::create();
    $staffed->business->update(['location_text' => 'Escazú, frente al parque', 'settings' => (object) $settings]);
    $staffed->owner->update(['name' => 'Ana Solís']);

    scopeRescheduleTo($staffed->business->id);

    $day = rescheduleDay();
    $weekday = (int) $day->format('N');

    $service = ServiceModel::factory()->create([
        'business_id' => $staffed->business->id,
        'name' => 'Prueba guiada',
        'duration_minutes' => 60,
        'buffer_minutes' => 15,
        'max_perfumes' => 3,
        'is_active' => true,
    ]);

    BusinessHoursModel::factory()->create([
        'business_id' => $staffed->business->id,
        'weekday' => $weekday,
        'opens_at' => '09:00',
        'closes_at' => '13:00',
    ]);

    $member = MemberModel::query()
        ->where('business_id', $staffed->business->id)
        ->where('user_id', $staffed->owner->id)
        ->firstOrFail();

    StaffScheduleModel::factory()->create([
        'business_id' => $staffed->business->id,
        'member_id' => $member->id,
        'weekday' => $weekday,
        'starts_at' => '09:00',
        'ends_at' => '13:00',
    ]);

    $appointment = appointmentOfCustomer($staffed, $service->id, $member->id, $day->setTime(9, 0));

    return [
        'staffed' => $staffed,
        'service' => $service,
        'day' => $day,
        'member' => $member->id,
        'appointment' => $appointment,
        'perfume' => PerfumeModel::factory()->create(['business_id' => $staffed->business->id])->id,
    ];
}

/** Book one appointment of the store for the customer of the scenario, or for another one when it is given. */
function appointmentOfCustomer(StaffedBusiness $staffed, string $serviceId, string $memberId, DateTimeImmutable $start, string $status = 'confirmed', ?string $customerId = null): string
{
    $customerId ??= DB::table('customers')
        ->where('business_id', $staffed->business->id)
        ->where('user_id', $staffed->customer->id)
        ->value('id');

    $appointment = AppointmentModel::factory()->create([
        'business_id' => $staffed->business->id,
        'service_id' => $serviceId,
        'customer_id' => $customerId,
        'member_id' => $memberId,
        'starts_at' => $start,
        'ends_at' => $start->modify('+60 minutes'),
        'blocked_until' => $start->modify('+75 minutes'),
        'status' => $status,
        'cancelled_at' => $status === 'cancelled' ? $start->modify('-2 days') : null,
        'cancel_reason' => $status === 'cancelled' ? 'Avisó con tiempo' : null,
    ]);

    scopeRescheduleTo($staffed->business->id);

    return (string) $appointment->id;
}

/** The URL of the customer route that moves one of its appointments. */
function rescheduleUrl(string $appointmentId): string
{
    return '/api/v1/cuenta/citas/'.$appointmentId;
}

/** The URL of the panel route that moves an appointment of the store of the session. */
function panelRescheduleUrl(string $appointmentId): string
{
    return '/api/v1/panel/citas/'.$appointmentId;
}

/** The instant of an hour of the day of the scenario, in ISO 8601 with the offset of the store. */
function rescheduleAt(DateTimeImmutable $day, string $time): string
{
    [$hours, $minutes] = array_map(intval(...), explode(':', $time));

    return $day->setTime($hours, $minutes)->format(DATE_ATOM);
}

/**
 * The stored appointment read with the business of the scenario pinned.
 *
 * @return array<string, mixed>
 */
function storedAppointment(string $businessId, string $appointmentId): array
{
    scopeRescheduleTo($businessId);

    return (array) DB::table('appointments')->where('id', $appointmentId)->firstOrFail();
}

test('moves the appointment of the customer and recalculates its end and its blocked range', function (): void {
    $scenario = rescheduleScenario();
    actingAsCustomer($scenario['staffed']);

    $response = patchJson(rescheduleUrl($scenario['appointment']), ['inicia_en' => rescheduleAt($scenario['day'], '10:15')])
        ->assertOk()
        ->assertJsonPath('data.estado', 'confirmada')
        ->assertJsonPath('data.persona.nombre', 'Ana Solís')
        ->assertJsonPath('data.inicia_en', rescheduleAt($scenario['day'], '10:15'))
        ->assertJsonPath('data.termina_en', rescheduleAt($scenario['day'], '11:15'));

    $row = storedAppointment($scenario['staffed']->business->id, (string) $response->json('data.id'));

    expect(CarbonImmutable::parse((string) $row['starts_at'])->format('H:i'))->toBe('10:15')
        ->and(CarbonImmutable::parse((string) $row['ends_at'])->format('H:i'))->toBe('11:15')
        // The margin of the service stays blocked after the new end.
        ->and(CarbonImmutable::parse((string) $row['blocked_until'])->format('H:i'))->toBe('11:30');
});

test('answers a conflict when another active appointment already blocks the new slot', function (): void {
    $scenario = rescheduleScenario();
    appointmentOfCustomer($scenario['staffed'], $scenario['service']->id, $scenario['member'], $scenario['day']->setTime(11, 0));

    actingAsCustomer($scenario['staffed']);

    patchJson(rescheduleUrl($scenario['appointment']), ['inicia_en' => rescheduleAt($scenario['day'], '11:00')])
        ->assertConflict()
        ->assertJsonPath('detail', 'Ese horario ya no está disponible.');

    $row = storedAppointment($scenario['staffed']->business->id, $scenario['appointment']);

    expect(CarbonImmutable::parse((string) $row['starts_at'])->format('H:i'))->toBe('09:00');
});

test('refuses a change inside the deadline the store gives and leaves the appointment untouched', function (): void {
    $scenario = rescheduleScenario();
    scopeRescheduleTo($scenario['staffed']->business->id);

    DB::table('appointments')->where('id', $scenario['appointment'])->update([
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'blocked_until' => now()->addHours(2)->addMinutes(15),
    ]);

    actingAsCustomer($scenario['staffed']);

    patchJson(rescheduleUrl($scenario['appointment']), ['inicia_en' => rescheduleAt($scenario['day'], '10:00')])
        ->assertConflict()
        ->assertJsonPath('detail', 'La cita solo se cambia o se cancela hasta 2 horas antes.');

    $row = storedAppointment($scenario['staffed']->business->id, $scenario['appointment']);

    expect(CarbonImmutable::parse((string) $row['starts_at'])->isSameDay(now()->addHour()))->toBeTrue();
});

test('refuses a change of an appointment that is not confirmed', function (): void {
    $scenario = rescheduleScenario();
    $appointment = appointmentOfCustomer($scenario['staffed'], $scenario['service']->id, $scenario['member'], $scenario['day']->setTime(9, 0), 'cancelled');

    actingAsCustomer($scenario['staffed']);

    patchJson(rescheduleUrl($appointment), ['inicia_en' => rescheduleAt($scenario['day'], '10:00')])
        ->assertConflict()
        ->assertJsonPath('status', 409);
});

test('hides the appointment of another customer and refuses the route to the staff', function (): void {
    $scenario = rescheduleScenario();

    $foreignCustomer = CustomerModel::factory()->create([
        'business_id' => $scenario['staffed']->business->id,
        'user_id' => UserModel::factory()->create()->id,
    ])->id;

    $foreign = appointmentOfCustomer(
        $scenario['staffed'],
        $scenario['service']->id,
        $scenario['member'],
        $scenario['day']->setTime(12, 0),
        'confirmed',
        $foreignCustomer,
    );

    actingAsCustomer($scenario['staffed']);

    patchJson(rescheduleUrl($foreign), ['inicia_en' => rescheduleAt($scenario['day'], '10:15')])->assertNotFound();

    actingAsStaff($scenario['staffed']);

    patchJson(rescheduleUrl($scenario['appointment']), ['inicia_en' => rescheduleAt($scenario['day'], '10:15')])
        ->assertForbidden();
});

test('lets the owner move an appointment of its store and keeps the staff and the customers out', function (): void {
    $scenario = rescheduleScenario();
    actingAsOwner($scenario['staffed']);

    patchJson(panelRescheduleUrl($scenario['appointment']), ['inicia_en' => rescheduleAt($scenario['day'], '10:15')])
        ->assertOk()
        ->assertJsonPath('data.inicia_en', rescheduleAt($scenario['day'], '10:15'));

    actingAsStaff($scenario['staffed']);
    patchJson(panelRescheduleUrl($scenario['appointment']), ['inicia_en' => rescheduleAt($scenario['day'], '11:00')])
        ->assertForbidden();

    actingAsCustomer($scenario['staffed']);
    patchJson(panelRescheduleUrl($scenario['appointment']), ['inicia_en' => rescheduleAt($scenario['day'], '11:00')])
        ->assertForbidden();
});

test('hides the appointment of another store from the panel and answers a conflict when the slot is taken', function (): void {
    $scenario = rescheduleScenario();
    $other = rescheduleScenario();

    actingAsOwner($scenario['staffed']);

    patchJson(panelRescheduleUrl($other['appointment']), ['inicia_en' => rescheduleAt($other['day'], '10:15')])
        ->assertNotFound();

    appointmentOfCustomer($scenario['staffed'], $scenario['service']->id, $scenario['member'], $scenario['day']->setTime(11, 0));

    patchJson(panelRescheduleUrl($scenario['appointment']), ['inicia_en' => rescheduleAt($scenario['day'], '11:00')])
        ->assertConflict()
        ->assertJsonPath('detail', 'Ese horario ya no está disponible.');
});

test('validates the new start of the appointment', function (): void {
    $scenario = rescheduleScenario();
    actingAsCustomer($scenario['staffed']);

    patchJson(rescheduleUrl($scenario['appointment']), [])
        ->assertUnprocessable()
        ->assertJsonPath('errores.inicia_en.0', 'El campo inicio de la cita es obligatorio.');

    patchJson(rescheduleUrl($scenario['appointment']), ['inicia_en' => $scenario['day']->format('Y-m-d').' 10:00:00'])
        ->assertUnprocessable()
        ->assertJsonStructure(['errores' => ['inicia_en']]);

    patchJson(rescheduleUrl($scenario['appointment']), ['inicia_en' => rescheduleAt($scenario['day']->modify('+31 days'), '10:00')])
        ->assertUnprocessable()
        ->assertJsonPath('errores.inicia_en.0', __('scheduling.booking.outside_window'));
});
