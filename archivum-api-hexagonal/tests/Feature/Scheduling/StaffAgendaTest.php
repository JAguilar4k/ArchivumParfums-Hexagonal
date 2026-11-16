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
function staffAgendaZone(): DateTimeZone
{
    return new DateTimeZone((string) config('app.timezone'));
}

/**
 * A store with its owner, two people who attend appointments, a customer, a service and two perfumes.
 *
 * @return array{business: BusinessModel, owner: UserModel, advisor: UserModel, other: UserModel, owner_member: string, advisor_member: string, other_member: string, customer: string, service: string, perfume: string, second_perfume: string}
 */
function staffAgendaStore(string $name = 'Archivum Parfums'): array
{
    $business = BusinessModel::factory()->create(['name' => $name]);
    app(BusinessTransaction::class)->scope($business->id);

    $owner = UserModel::factory()->create(['name' => 'Dueña de la tienda']);
    $advisor = UserModel::factory()->create(['name' => 'Ana Solís']);
    $other = UserModel::factory()->create(['name' => 'Beto Vargas']);
    $customer = UserModel::factory()->create(['name' => 'Mariana Rojas']);

    return [
        'business' => $business,
        'owner' => $owner,
        'advisor' => $advisor,
        'other' => $other,
        'owner_member' => MemberModel::factory()->owner()->create(['business_id' => $business->id, 'user_id' => $owner->id])->id,
        'advisor_member' => MemberModel::factory()->create([
            'business_id' => $business->id,
            'user_id' => $advisor->id,
            'attends_appointments' => true,
        ])->id,
        'other_member' => MemberModel::factory()->create([
            'business_id' => $business->id,
            'user_id' => $other->id,
            'attends_appointments' => true,
        ])->id,
        'customer' => CustomerModel::factory()->create(['business_id' => $business->id, 'user_id' => $customer->id])->id,
        'service' => ServiceModel::factory()->create(['business_id' => $business->id, 'name' => 'Prueba guiada'])->id,
        'perfume' => PerfumeModel::factory()->create(['business_id' => $business->id, 'brand' => 'Lattafa', 'name' => 'Asad'])->id,
        'second_perfume' => PerfumeModel::factory()->create(['business_id' => $business->id, 'brand' => 'Armaf', 'name' => 'Club de Nuit'])->id,
    ];
}

/**
 * Book an appointment of the store that many days from now at that hour, with the perfumes the staff must carry.
 *
 * @param  array{business: BusinessModel, customer: string, service: string, perfume: string, second_perfume: string}  $store
 * @param  list<string>  $perfumes
 */
function staffAgendaAppointment(array $store, string $memberId, int $days, string $time = '10:00', string $status = 'confirmed', array $perfumes = []): string
{
    app(BusinessTransaction::class)->scope($store['business']->id);

    $start = (new DateTimeImmutable('now', staffAgendaZone()))->setTime(0, 0)->modify("{$days} days");
    [$hour, $minute] = array_map(intval(...), explode(':', $time));
    $start = $start->setTime($hour, $minute);

    $appointment = AppointmentModel::factory()->create([
        'business_id' => $store['business']->id,
        'service_id' => $store['service'],
        'customer_id' => $store['customer'],
        'member_id' => $memberId,
        'starts_at' => $start,
        'ends_at' => $start->modify('+45 minutes'),
        'blocked_until' => $start->modify('+60 minutes'),
        'status' => $status,
    ]);

    foreach ($perfumes as $perfume) {
        DB::table('appointment_perfumes')->insert([
            'id' => (string) Str::uuid7(),
            'business_id' => $store['business']->id,
            'appointment_id' => $appointment->id,
            'perfume_id' => $perfume,
        ]);
    }

    return $appointment->id;
}

/** Sign in the owner of the store. */
function actingAsStaffAgendaOwner(UserModel $user): void
{
    Sanctum::actingAs($user, Ability::namesGrantedTo(AccountRole::Owner));
}

/** Sign in a person of the staff of the store. */
function actingAsStaffAgendaAdvisor(UserModel $user): void
{
    Sanctum::actingAs($user, Ability::namesGrantedTo(AccountRole::Staff));
}

/** The day of the store that many days from now, as the panel sends it. */
function staffAgendaDay(int $days): string
{
    return (new DateTimeImmutable('now', staffAgendaZone()))->modify("{$days} days")->format('Y-m-d');
}

it('shows the owner every appointment of the day with the customer, the service, the person and the perfumes', function () {
    $store = staffAgendaStore();
    staffAgendaAppointment($store, $store['advisor_member'], 1, '11:30', 'confirmed', [$store['second_perfume']]);
    staffAgendaAppointment($store, $store['other_member'], 1, '10:00', 'confirmed', [$store['perfume'], $store['second_perfume']]);

    actingAsStaffAgendaOwner($store['owner']);

    $response = getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(1))->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0.persona.nombre'))->toBe('Beto Vargas')
        ->and($response->json('data.1.persona.nombre'))->toBe('Ana Solís')
        ->and($response->json('data.0.estado'))->toBe('confirmada')
        ->and($response->json('data.0.cliente.nombre'))->toBe('Mariana Rojas')
        ->and($response->json('data.0.servicio.nombre'))->toBe('Prueba guiada')
        ->and($response->json('data.0.inicia_en'))->toStartWith(staffAgendaDay(1).'T10:00:00')
        ->and($response->json('data.0.termina_en'))->toStartWith(staffAgendaDay(1).'T10:45:00')
        ->and($response->json('data.0.perfumes'))->toBe([
            ['id' => $store['perfume'], 'marca' => 'Lattafa', 'nombre' => 'Asad'],
            ['id' => $store['second_perfume'], 'marca' => 'Armaf', 'nombre' => 'Club de Nuit'],
        ]);
});

it('narrows the agenda of the owner to one person and to one state', function () {
    $store = staffAgendaStore();
    staffAgendaAppointment($store, $store['advisor_member'], 1, '10:00', 'confirmed');
    staffAgendaAppointment($store, $store['other_member'], 1, '12:00', 'completed');

    actingAsStaffAgendaOwner($store['owner']);

    expect(getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(1).'&persona='.$store['other_member'])->assertOk()->json('data'))
        ->toHaveCount(1)
        ->and(getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(1).'&estado=atendida')->assertOk()->json('data'))
        ->toHaveCount(1)
        ->and(getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(1).'&estado=atendida')->json('data.0.persona.nombre'))
        ->toBe('Beto Vargas');
});

it('shows a collaborator only its own appointments even when the query names another person', function () {
    $store = staffAgendaStore();
    staffAgendaAppointment($store, $store['advisor_member'], 1, '10:00');
    staffAgendaAppointment($store, $store['other_member'], 1, '12:00');

    actingAsStaffAgendaAdvisor($store['advisor']);

    $response = getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(1).'&persona='.$store['other_member'])->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.persona.nombre'))->toBe('Ana Solís');
});

it('refuses a customer and a visitor', function () {
    $store = staffAgendaStore();
    staffAgendaAppointment($store, $store['advisor_member'], 1);

    $customerUser = UserModel::query()->whereKey(
        CustomerModel::query()->whereKey($store['customer'])->value('user_id'),
    )->firstOrFail();

    Sanctum::actingAs($customerUser, Ability::namesGrantedTo(AccountRole::Customer));

    getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(1))
        ->assertStatus(403)
        ->assertJsonPath('status', 403);

    auth()->forgetGuards();

    getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(1))
        ->assertStatus(401)
        ->assertJsonPath('status', 401);
});

it('refuses a range of more than 31 days and one that ends before it starts', function () {
    $store = staffAgendaStore();
    actingAsStaffAgendaOwner($store['owner']);

    getJson('/api/v1/panel/citas?desde=2026-12-01&hasta=2026-11-30')
        ->assertStatus(422)
        ->assertJsonPath('status', 422)
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->assertJsonStructure(['errores' => ['hasta']]);

    getJson('/api/v1/panel/citas?desde=2026-01-01&hasta=2026-02-15')
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['hasta']]);

    getJson('/api/v1/panel/citas?fecha=2026-13-01')
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['fecha']]);
});

it('never shows the appointments of another store', function () {
    $store = staffAgendaStore();
    staffAgendaAppointment($store, $store['advisor_member'], 1, '10:00');

    $another = staffAgendaStore('Esencias del Norte');
    staffAgendaAppointment($another, $another['advisor_member'], 1, '11:00');

    actingAsStaffAgendaOwner($store['owner']);

    $response = getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(1))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.persona.nombre'))->toBe('Ana Solís')
        ->and($response->json('data.0.cliente.nombre'))->toBe('Mariana Rojas');
});

it('reads the agenda in the time zone of the store', function () {
    $store = staffAgendaStore();
    $id = staffAgendaAppointment($store, $store['advisor_member'], 1, '23:30');

    actingAsStaffAgendaOwner($store['owner']);

    $response = getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(1))->assertOk();

    expect($response->json('data.0.id'))->toBe($id)
        ->and($response->json('data.0.inicia_en'))->toStartWith(staffAgendaDay(1).'T23:30:00')
        ->and(getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(2))->assertOk()->json('data'))->toBe([]);
});

it('loads the agenda with a fixed number of queries', function () {
    $store = staffAgendaStore();
    staffAgendaAppointment($store, $store['advisor_member'], 1, '10:00', 'confirmed', [$store['perfume']]);

    actingAsStaffAgendaOwner($store['owner']);

    DB::enableQueryLog();
    DB::flushQueryLog();
    getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(1))->assertOk();
    $one = count(DB::getQueryLog());

    staffAgendaAppointment($store, $store['other_member'], 1, '12:00', 'confirmed', [$store['perfume'], $store['second_perfume']]);
    staffAgendaAppointment($store, $store['advisor_member'], 1, '13:00', 'confirmed', [$store['second_perfume']]);

    DB::flushQueryLog();
    getJson('/api/v1/panel/citas?fecha='.staffAgendaDay(1))->assertOk();
    $three = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($three)->toBe($one);
});
