<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;

uses(RefreshDatabaseAsOwner::class);

/** Time zone of the stores the scenario belongs to. */
function appointmentsZone(): DateTimeZone
{
    return new DateTimeZone((string) config('app.timezone'));
}

/**
 * A store where the account is a customer, with a service, a person who attends appointments and a perfume.
 *
 * @return array{business: BusinessModel, customer: string, service: string, member: string, perfume: string}
 */
function appointmentStore(UserModel $user, string $name): array
{
    $business = BusinessModel::factory()->create(['name' => $name, 'location_text' => 'Escazú, frente al parque']);
    app(BusinessTransaction::class)->scope($business->id);

    $staff = UserModel::factory()->create(['name' => 'Ana Solís']);

    return [
        'business' => $business,
        'customer' => CustomerModel::factory()->create(['business_id' => $business->id, 'user_id' => $user->id])->id,
        'service' => ServiceModel::factory()->create(['business_id' => $business->id, 'name' => 'Prueba guiada'])->id,
        'member' => MemberModel::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'attends_appointments' => true,
        ])->id,
        'perfume' => PerfumeModel::factory()->create(['business_id' => $business->id, 'name' => 'Aroma de prueba'])->id,
    ];
}

/**
 * Book an appointment of the store that many days from now, negative when it already happened.
 *
 * @param  array{business: BusinessModel, customer: string, service: string, member: string, perfume: string}  $store
 */
function appointmentAt(array $store, int $days, string $status = 'confirmed'): string
{
    $start = (new DateTimeImmutable('now', appointmentsZone()))->setTime(10, 0)->modify("{$days} days");

    $appointment = AppointmentModel::factory()->create([
        'business_id' => $store['business']->id,
        'service_id' => $store['service'],
        'customer_id' => $store['customer'],
        'member_id' => $store['member'],
        'starts_at' => $start,
        'ends_at' => $start->modify('+45 minutes'),
        'blocked_until' => $start->modify('+60 minutes'),
        'status' => $status,
    ]);

    app(BusinessTransaction::class)->scope($store['business']->id);

    DB::table('appointment_perfumes')->insert([
        'id' => (string) Str::uuid7(),
        'business_id' => $store['business']->id,
        'appointment_id' => $appointment->id,
        'perfume_id' => $store['perfume'],
    ]);

    return $appointment->id;
}

/** Sign in the account as the customer it is. */
function actingAsAppointmentCustomer(UserModel $user): void
{
    Sanctum::actingAs($user, Ability::namesGrantedTo(AccountRole::Customer));
}

test('lists the upcoming appointments of the customer in every store, the closest first', function (): void {
    $user = UserModel::factory()->create(['name' => 'Elena Vargas']);
    $first = appointmentStore($user, 'Perfumería Centro');
    $second = appointmentStore($user, 'Perfumería Norte');

    $later = appointmentAt($first, 5);
    $sooner = appointmentAt($second, 2);
    appointmentAt($first, -3);

    actingAsAppointmentCustomer($user);

    $response = getJson('/api/v1/cuenta/citas')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$sooner, $later])
        ->and($response->json('data.0.tienda.nombre'))->toBe('Perfumería Norte')
        ->and($response->json('data.0.estado'))->toBe('confirmada')
        ->and($response->json('data.0.servicio.nombre'))->toBe('Prueba guiada')
        ->and($response->json('data.0.persona.nombre'))->toBe('Ana Solís')
        ->and($response->json('data.0.lugar_cita'))->toBe('Escazú, frente al parque')
        ->and($response->json('data.0.perfumes.0.nombre'))->toBe('Aroma de prueba')
        ->and($response->json('meta.total'))->toBe(2);
});

test('lists the past appointments of the customer, the most recent first', function (): void {
    $user = UserModel::factory()->create();
    $first = appointmentStore($user, 'Perfumería Centro');
    $second = appointmentStore($user, 'Perfumería Norte');

    $recent = appointmentAt($first, -1, 'completed');
    $older = appointmentAt($second, -10);
    appointmentAt($first, 4);

    actingAsAppointmentCustomer($user);

    $response = getJson('/api/v1/cuenta/citas?periodo=pasadas')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$recent, $older])
        ->and($response->json('data.0.estado'))->toBe('atendida');
});

test('reads one appointment of the customer and hides the ones of other customers', function (): void {
    $user = UserModel::factory()->create();
    $store = appointmentStore($user, 'Perfumería Centro');
    $own = appointmentAt($store, 3);

    $other = UserModel::factory()->create();
    $foreign = appointmentStore($other, 'Perfumería Norte');
    $foreignAppointment = appointmentAt($foreign, 3);

    actingAsAppointmentCustomer($user);

    getJson('/api/v1/cuenta/citas/'.$own)->assertOk()
        ->assertJsonPath('data.id', $own)
        ->assertJsonPath('data.tienda.nombre', 'Perfumería Centro');

    getJson('/api/v1/cuenta/citas/'.$foreignAppointment)->assertNotFound()
        ->assertJsonPath('status', 404);

    getJson('/api/v1/cuenta/citas/'.Str::uuid7())->assertNotFound();
});

test('refuses the listing without a session and to the staff of a store', function (): void {
    $user = UserModel::factory()->create();
    appointmentStore($user, 'Perfumería Centro');

    getJson('/api/v1/cuenta/citas')->assertUnauthorized();

    Sanctum::actingAs($user, Ability::namesGrantedTo(AccountRole::Staff));

    getJson('/api/v1/cuenta/citas')->assertForbidden();
});

test('answers an empty listing for a customer without appointments', function (): void {
    $user = UserModel::factory()->create();
    appointmentStore($user, 'Perfumería Centro');
    actingAsAppointmentCustomer($user);

    $response = getJson('/api/v1/cuenta/citas')->assertOk();

    expect($response->json('data'))->toBe([])
        ->and($response->json('meta.total'))->toBe(0);
});
