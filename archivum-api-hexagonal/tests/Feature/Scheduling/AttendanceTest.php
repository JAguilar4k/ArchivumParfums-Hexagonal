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
use Src\Scheduling\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\postJson;

uses(RefreshDatabaseAsOwner::class);

/**
 * A store with its owner, two people who attend appointments, a customer and a service.
 *
 * @return array{business: BusinessModel, owner: UserModel, advisor: UserModel, other: UserModel, customer: UserModel, owner_member: string, advisor_member: string, other_member: string, customer_id: string, service: string}
 */
function attendanceStore(string $name = 'Archivum Parfums'): array
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
        'customer' => $customer,
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
        'customer_id' => CustomerModel::factory()->create(['business_id' => $business->id, 'user_id' => $customer->id])->id,
        'service' => ServiceModel::factory()->create(['business_id' => $business->id, 'name' => 'Prueba guiada'])->id,
    ];
}

/**
 * Book an appointment of the store that many days from now with the state the scenario needs.
 *
 * @param  array{business: BusinessModel, customer_id: string, service: string}  $store
 */
function attendanceAppointment(array $store, string $memberId, string $status = 'confirmed', int $days = 1): string
{
    app(BusinessTransaction::class)->scope($store['business']->id);

    $start = (new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone'))))
        ->setTime(10, 0)
        ->modify("{$days} days");

    return AppointmentModel::factory()->create([
        'business_id' => $store['business']->id,
        'service_id' => $store['service'],
        'customer_id' => $store['customer_id'],
        'member_id' => $memberId,
        'starts_at' => $start,
        'ends_at' => $start->modify('+45 minutes'),
        'blocked_until' => $start->modify('+60 minutes'),
        'status' => $status,
    ])->id;
}

/**
 * The state the table keeps for an appointment, read as the application of the store does.
 *
 * @param  array{business: BusinessModel}  $store
 */
function attendanceStatus(array $store, string $appointmentId): string
{
    return (string) app(BusinessTransaction::class)->run(
        $store['business']->id,
        fn (): mixed => DB::table('appointments')->where('id', $appointmentId)->value('status'),
    );
}

/** Sign in the owner of the store. */
function actingAsAttendanceOwner(UserModel $user): void
{
    Sanctum::actingAs($user, Ability::namesGrantedTo(AccountRole::Owner));
}

/** Sign in a person of the staff of the store. */
function actingAsAttendanceAdvisor(UserModel $user): void
{
    Sanctum::actingAs($user, Ability::namesGrantedTo(AccountRole::Staff));
}

/** The URL of the attendance of one appointment. */
function attendanceUrl(string $appointmentId): string
{
    return '/api/v1/panel/citas/'.$appointmentId.'/asistencia';
}

it('marks a confirmed appointment as attended', function () {
    $store = attendanceStore();
    $id = attendanceAppointment($store, $store['advisor_member']);

    actingAsAttendanceAdvisor($store['advisor']);

    $response = postJson(attendanceUrl($id), ['asistio' => true])->assertOk();

    expect($response->json('data.estado'))->toBe('atendida')
        ->and($response->json('data.id'))->toBe($id)
        ->and(attendanceStatus($store, $id))->toBe('completed');
});

it('marks a confirmed appointment as not attended', function () {
    $store = attendanceStore();
    $id = attendanceAppointment($store, $store['advisor_member']);

    actingAsAttendanceAdvisor($store['advisor']);

    $response = postJson(attendanceUrl($id), ['asistio' => false])->assertOk();

    expect($response->json('data.estado'))->toBe('no_asistio')
        ->and(attendanceStatus($store, $id))->toBe('no_show');
});

it('lets a collaborator mark only its own appointments', function () {
    $store = attendanceStore();
    $own = attendanceAppointment($store, $store['advisor_member']);
    $foreign = attendanceAppointment($store, $store['other_member']);

    actingAsAttendanceAdvisor($store['advisor']);

    postJson(attendanceUrl($own), ['asistio' => true])->assertOk();

    postJson(attendanceUrl($foreign), ['asistio' => true])
        ->assertStatus(404)
        ->assertJsonPath('status', 404);

    expect(attendanceStatus($store, $foreign))->toBe('confirmed');
});

it('lets the owner mark any appointment of the store and keeps a customer out', function () {
    $store = attendanceStore();
    $id = attendanceAppointment($store, $store['other_member']);

    actingAsAttendanceOwner($store['owner']);

    postJson(attendanceUrl($id), ['asistio' => true])->assertOk();

    expect(attendanceStatus($store, $id))->toBe('completed');

    Sanctum::actingAs($store['customer'], Ability::namesGrantedTo(AccountRole::Customer));

    postJson(attendanceUrl($id), ['asistio' => true])->assertStatus(403)->assertJsonPath('status', 403);

    auth()->forgetGuards();

    postJson(attendanceUrl($id), ['asistio' => true])->assertStatus(401)->assertJsonPath('status', 401);
});

it('answers as not found for an appointment of another store', function () {
    $store = attendanceStore();

    $another = attendanceStore('Esencias del Norte');
    $foreign = attendanceAppointment($another, $another['advisor_member']);

    actingAsAttendanceOwner($store['owner']);

    postJson(attendanceUrl($foreign), ['asistio' => true])
        ->assertStatus(404)
        ->assertJsonPath('status', 404);

    expect(attendanceStatus($another, $foreign))->toBe('confirmed');
});

it('refuses to mark an appointment that is not confirmed and leaves it as it was', function (string $status) {
    $store = attendanceStore();
    $id = attendanceAppointment($store, $store['advisor_member'], $status);

    actingAsAttendanceOwner($store['owner']);

    postJson(attendanceUrl($id), ['asistio' => true])
        ->assertStatus(409)
        ->assertJsonPath('status', 409)
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/conflicto');

    expect(attendanceStatus($store, $id))->toBe($status);
})->with(['cancelled', 'completed', 'no_show', 'expired']);

it('does not mark an appointment twice', function () {
    $store = attendanceStore();
    $id = attendanceAppointment($store, $store['advisor_member']);

    actingAsAttendanceAdvisor($store['advisor']);

    postJson(attendanceUrl($id), ['asistio' => false])->assertOk();

    postJson(attendanceUrl($id), ['asistio' => true])
        ->assertStatus(409)
        ->assertJsonPath('status', 409);

    expect(attendanceStatus($store, $id))->toBe('no_show');
});

it('requires a boolean attendance', function (array $body) {
    $store = attendanceStore();
    $id = attendanceAppointment($store, $store['advisor_member']);

    actingAsAttendanceAdvisor($store['advisor']);

    postJson(attendanceUrl($id), $body)
        ->assertStatus(422)
        ->assertJsonPath('status', 422)
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->assertJsonStructure(['errores' => ['asistio']]);

    expect(attendanceStatus($store, $id))->toBe('confirmed');
})->with([
    'missing' => [[]],
    'not a boolean' => [['asistio' => 'tal vez']],
    'null' => [['asistio' => null]],
]);

it('answers as not found for an appointment that does not exist', function () {
    $store = attendanceStore();
    actingAsAttendanceOwner($store['owner']);

    postJson(attendanceUrl((string) Str::uuid7()), ['asistio' => true])
        ->assertStatus(404)
        ->assertJsonPath('status', 404);

    postJson(attendanceUrl('no-es-un-uuid'), ['asistio' => true])
        ->assertStatus(404)
        ->assertJsonPath('status', 404);
});
