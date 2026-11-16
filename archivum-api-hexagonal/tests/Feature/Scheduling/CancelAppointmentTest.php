<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

use function Pest\Laravel\postJson;

uses(RefreshDatabaseAsOwner::class);

/** Pin the transaction of the test to the business, as the middleware of the request does. */
function cancelScopeTo(string $businessId): void
{
    app(BusinessTransaction::class)->scope($businessId);
}

/**
 * A store of the seeded staff, open from 09:00 to 13:00, with a 60 minute service, one perfume and one appointment of
 * its customer at 09:00 of the day of the scenario, a week ahead.
 *
 * @return array{staffed: StaffedBusiness, service: ServiceModel, day: DateTimeImmutable, member: string, appointment: string, perfume: string}
 */
function cancelScenario(): array
{
    $staffed = StaffedBusiness::create();
    $staffed->business->update(['location_text' => 'Escazú, frente al parque']);
    $staffed->owner->update(['name' => 'Ana Solís']);

    cancelScopeTo($staffed->business->id);

    $day = (new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone'))))->setTime(0, 0)->modify('+7 days');
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

    return [
        'staffed' => $staffed,
        'service' => $service,
        'day' => $day,
        'member' => $member->id,
        'appointment' => cancelAppointmentOf($staffed, $service->id, $member->id, $day->setTime(9, 0)),
        'perfume' => PerfumeModel::factory()->create(['business_id' => $staffed->business->id])->id,
    ];
}

/** Book one appointment of the store for its customer, or for another one when it is given. */
function cancelAppointmentOf(StaffedBusiness $staffed, string $serviceId, string $memberId, DateTimeImmutable $start, string $status = 'confirmed', ?string $customerId = null): string
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

    cancelScopeTo($staffed->business->id);

    return (string) $appointment->id;
}

/** The instant of an hour of the day of the scenario, in ISO 8601 with the offset of the store. */
function cancelAt(DateTimeImmutable $day, string $time): string
{
    [$hours, $minutes] = array_map(intval(...), explode(':', $time));

    return $day->setTime($hours, $minutes)->format(DATE_ATOM);
}

/**
 * The stored appointment read with the business of the scenario pinned.
 *
 * @return array<string, mixed>
 */
function cancelStored(string $businessId, string $appointmentId): array
{
    cancelScopeTo($businessId);

    return (array) DB::table('appointments')->where('id', $appointmentId)->firstOrFail();
}

/** The URL of the customer route that cancels one of its appointments. */
function cancelUrl(string $appointmentId): string
{
    return '/api/v1/cuenta/citas/'.$appointmentId.'/cancelacion';
}

/** The URL of the panel route that cancels an appointment of the store of the session. */
function panelCancelUrl(string $appointmentId): string
{
    return '/api/v1/panel/citas/'.$appointmentId.'/cancelacion';
}

test('cancels the appointment of the customer, keeps the reason and frees the slot again', function (): void {
    $scenario = cancelScenario();
    actingAsCustomer($scenario['staffed']);

    postJson(cancelUrl($scenario['appointment']), ['motivo' => 'Me surgió un viaje'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'cancelada');

    $row = cancelStored($scenario['staffed']->business->id, $scenario['appointment']);

    expect($row['status'])->toBe('cancelled')
        ->and($row['cancelled_at'])->not->toBeNull()
        ->and($row['cancel_reason'])->toBe('Me surgió un viaje');

    // The cancelled row stops blocking the slot, so the same customer books it again on the same instant.
    postJson('/api/v1/tiendas/'.$scenario['staffed']->business->slug.'/citas', [
        'servicio_id' => $scenario['service']->id,
        'inicia_en' => cancelAt($scenario['day'], '09:00'),
        'persona_id' => $scenario['member'],
        'perfumes' => [$scenario['perfume']],
    ])->assertCreated();
});

test('refuses a cancellation inside the deadline the store gives and leaves the appointment confirmed', function (): void {
    $scenario = cancelScenario();
    cancelScopeTo($scenario['staffed']->business->id);

    DB::table('appointments')->where('id', $scenario['appointment'])->update([
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'blocked_until' => now()->addHours(2)->addMinutes(15),
    ]);

    actingAsCustomer($scenario['staffed']);

    postJson(cancelUrl($scenario['appointment']), [])
        ->assertConflict()
        ->assertJsonPath('detail', 'La cita solo se cambia o se cancela hasta 2 horas antes.');

    $row = cancelStored($scenario['staffed']->business->id, $scenario['appointment']);

    expect($row['status'])->toBe('confirmed')
        ->and($row['cancelled_at'])->toBeNull();
});

test('refuses a cancellation of an appointment that is not confirmed', function (): void {
    $scenario = cancelScenario();
    $appointment = cancelAppointmentOf($scenario['staffed'], $scenario['service']->id, $scenario['member'], $scenario['day']->setTime(12, 0), 'cancelled');

    actingAsCustomer($scenario['staffed']);

    postJson(cancelUrl($appointment), [])->assertConflict()->assertJsonPath('status', 409);
});

test('hides the appointment of another customer, an unknown one and the route of the staff', function (): void {
    $scenario = cancelScenario();

    $foreignCustomer = CustomerModel::factory()->create([
        'business_id' => $scenario['staffed']->business->id,
        'user_id' => UserModel::factory()->create()->id,
    ])->id;

    $foreign = cancelAppointmentOf(
        $scenario['staffed'],
        $scenario['service']->id,
        $scenario['member'],
        $scenario['day']->setTime(12, 0),
        'confirmed',
        $foreignCustomer,
    );

    actingAsCustomer($scenario['staffed']);

    postJson(cancelUrl($foreign), [])->assertNotFound();
    postJson(cancelUrl((string) Str::uuid7()), [])->assertNotFound();

    actingAsStaff($scenario['staffed']);

    postJson(cancelUrl($scenario['appointment']), [])->assertForbidden();
});

test('lets the owner cancel an appointment of its store and keeps the staff, the customers and other stores out', function (): void {
    $scenario = cancelScenario();
    $other = cancelScenario();

    actingAsOwner($scenario['staffed']);

    postJson(panelCancelUrl($other['appointment']), [])->assertNotFound();

    postJson(panelCancelUrl($scenario['appointment']), ['motivo' => 'La tienda cierra por mantenimiento'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'cancelada');

    $row = cancelStored($scenario['staffed']->business->id, $scenario['appointment']);

    expect($row['status'])->toBe('cancelled')
        ->and($row['cancel_reason'])->toBe('La tienda cierra por mantenimiento');

    actingAsStaff($other['staffed']);
    postJson(panelCancelUrl($other['appointment']), [])->assertForbidden();

    actingAsCustomer($other['staffed']);
    postJson(panelCancelUrl($other['appointment']), [])->assertForbidden();
});

test('the store may cancel an appointment its customer can no longer cancel', function (): void {
    $scenario = cancelScenario();
    cancelScopeTo($scenario['staffed']->business->id);

    DB::table('appointments')->where('id', $scenario['appointment'])->update([
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'blocked_until' => now()->addHours(2)->addMinutes(15),
    ]);

    actingAsOwner($scenario['staffed']);

    postJson(panelCancelUrl($scenario['appointment']), ['motivo' => 'La persona no llegó'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'cancelada');
});

test('validates the reason of the cancellation', function (): void {
    $scenario = cancelScenario();
    actingAsCustomer($scenario['staffed']);

    postJson(cancelUrl($scenario['appointment']), ['motivo' => str_repeat('a', 201)])
        ->assertUnprocessable()
        ->assertJsonStructure(['errores' => ['motivo']]);

    postJson(cancelUrl($scenario['appointment']), ['motivo' => ['no', 'es', 'texto']])
        ->assertUnprocessable()
        ->assertJsonStructure(['errores' => ['motivo']]);
});
