<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const BLOCKS_ENDPOINT = '/api/v1/panel/bloqueos';

beforeEach(function (): void {
    TwoBusinesses::create();
});

/** Add a person of the staff to a business and answer her identifier. */
function blockStaffMemberOf(string $businessId): string
{
    return MemberModel::factory()->staff()->create([
        'business_id' => $businessId,
        'user_id' => UserModel::factory()->create()->id,
    ])->id;
}

/**
 * One block of the agenda, as the table stores it.
 *
 * @return array<string, mixed>
 */
function scheduleBlockRow(string $businessId, ?string $memberId, string $startsAt, string $endsAt, string $source = 'manual'): array
{
    return [
        'id' => (string) Str::uuid7(),
        'business_id' => $businessId,
        'member_id' => $memberId,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'reason' => 'Inventario',
        'source' => $source,
    ];
}

/** Sign in the owner of the first business of the two of the tests. */
function blockOwner(): void
{
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);
}

it('creates a block of the whole store and answers 201 with its location', function (): void {
    blockOwner();

    $response = postJson(BLOCKS_ENDPOINT, [
        'inicia_en' => '2026-11-10T10:00:00-06:00',
        'termina_en' => '2026-11-10T12:00:00-06:00',
        'motivo' => 'Inventario',
    ]);

    $response->assertCreated();

    $id = (string) $response->json('data.id');

    expect($response->json('data'))->toMatchArray([
        'persona_id' => null,
        'inicia_en' => '2026-11-10T10:00:00-06:00',
        'termina_en' => '2026-11-10T12:00:00-06:00',
        'motivo' => 'Inventario',
        'origen' => 'manual',
    ])->and($response->headers->get('Location'))->toEndWith(BLOCKS_ENDPOINT.'/'.$id);

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));

    expect(DB::table('schedule_blocks')->where('id', $id)->value('source'))->toBe('manual')
        ->and(DB::table('schedule_blocks')->where('id', $id)->value('member_id'))->toBeNull();
});

it('creates a block of one person of the staff of the store', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($first);
    $member = blockStaffMemberOf($first);

    $this->scopeQueriesTo(null);
    blockOwner();

    $response = postJson(BLOCKS_ENDPOINT, [
        'persona_id' => $member,
        'inicia_en' => '2026-11-10T15:00:00-06:00',
        'termina_en' => '2026-11-10T16:00:00-06:00',
        'motivo' => 'Cita médica',
    ]);

    $response->assertCreated();

    expect($response->json('data.persona_id'))->toBe($member);
});

it('edits a manual block and deletes it with 204', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($first);
    $id = (string) Str::uuid7();
    DB::table('schedule_blocks')->insert([
        ...scheduleBlockRow($first, null, '2026-11-10 10:00:00-06', '2026-11-10 12:00:00-06'),
        'id' => $id,
    ]);

    $this->scopeQueriesTo(null);
    blockOwner();

    $edited = patchJson(BLOCKS_ENDPOINT.'/'.$id, ['motivo' => 'Inventario anual', 'termina_en' => '2026-11-10T13:00:00-06:00']);

    $edited->assertOk();

    expect($edited->json('data.motivo'))->toBe('Inventario anual')
        ->and($edited->json('data.termina_en'))->toBe('2026-11-10T13:00:00-06:00');

    deleteJson(BLOCKS_ENDPOINT.'/'.$id)->assertNoContent();

    $this->scopeQueriesTo($first);

    expect(DB::table('schedule_blocks')->where('id', $id)->count())->toBe(0);
});

it('answers 409 conflicto on a block that comes from the calendar', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($first);
    $id = (string) Str::uuid7();
    DB::table('schedule_blocks')->insert([
        ...scheduleBlockRow($first, null, '2026-11-11 09:00:00-06', '2026-11-11 10:00:00-06', 'google'),
        'id' => $id,
    ]);

    $this->scopeQueriesTo(null);
    blockOwner();

    $edited = patchJson(BLOCKS_ENDPOINT.'/'.$id, ['motivo' => 'Robado']);
    $deleted = deleteJson(BLOCKS_ENDPOINT.'/'.$id);

    expect($edited->status())->toBe(409)
        ->and($edited->json('type'))->toEndWith('/problemas/conflicto')
        ->and($deleted->status())->toBe(409);

    $this->scopeQueriesTo($first);

    expect(DB::table('schedule_blocks')->where('id', $id)->count())->toBe(1);
});

it('lists the blocks that cross the range and filters by person', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($first);
    $member = blockStaffMemberOf($first);
    $crossing = (string) Str::uuid7();
    $ofMember = (string) Str::uuid7();
    $outside = (string) Str::uuid7();
    DB::table('schedule_blocks')->insert([
        ...scheduleBlockRow($first, null, '2026-11-12 10:00:00-06', '2026-11-12 12:00:00-06'),
        'id' => $crossing,
    ]);
    DB::table('schedule_blocks')->insert([
        ...scheduleBlockRow($first, $member, '2026-11-12 15:00:00-06', '2026-11-12 16:00:00-06'),
        'id' => $ofMember,
    ]);
    DB::table('schedule_blocks')->insert([
        ...scheduleBlockRow($first, null, '2026-11-20 10:00:00-06', '2026-11-20 12:00:00-06'),
        'id' => $outside,
    ]);

    $this->scopeQueriesTo(null);
    blockOwner();

    $all = getJson(BLOCKS_ENDPOINT.'?desde=2026-11-12T00:00:00-06:00&hasta=2026-11-13T00:00:00-06:00');
    $onlyMember = getJson(BLOCKS_ENDPOINT.'?desde=2026-11-12T00:00:00-06:00&hasta=2026-11-13T00:00:00-06:00&persona='.$member);

    $all->assertOk();
    $onlyMember->assertOk();

    expect(array_column((array) $all->json('data'), 'id'))->toBe([$crossing, $ofMember])
        ->and(array_column((array) $onlyMember->json('data'), 'id'))->toBe([$ofMember]);
});

it('answers 422 with the detail of the field that is wrong, and writes nothing', function (array $payload, string $campo): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    blockOwner();

    $response = postJson(BLOCKS_ENDPOINT, [
        'inicia_en' => '2026-11-10T10:00:00-06:00',
        'termina_en' => '2026-11-10T12:00:00-06:00',
        'motivo' => 'Inventario',
        ...$payload,
    ]);

    $response->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json');

    expect($response->json('errores'))->toHaveKey($campo);

    $this->scopeQueriesTo($first);

    expect(DB::table('schedule_blocks')->where('business_id', $first)->count())->toBe(0);
})->with([
    'a block that ends before it starts' => [['termina_en' => '2026-11-10T09:00:00-06:00'], 'termina_en'],
    'an unknown person' => [['persona_id' => '019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f70'], 'persona_id'],
]);

it('answers 422 with the person field for a person of another business', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    $this->scopeQueriesTo($second);
    $foreign = blockStaffMemberOf($second);

    $this->scopeQueriesTo(null);
    blockOwner();

    $response = postJson(BLOCKS_ENDPOINT, [
        'persona_id' => $foreign,
        'inicia_en' => '2026-11-10T10:00:00-06:00',
        'termina_en' => '2026-11-10T12:00:00-06:00',
        'motivo' => 'Inventario',
    ]);

    expect($response->status())->toBe(422)
        ->and($response->json('errores'))->toHaveKey('persona_id');

    $this->scopeQueriesTo($first);
    expect(DB::table('schedule_blocks')->where('business_id', $first)->count())->toBe(0);

    $this->scopeQueriesTo($second);
    expect(DB::table('schedule_blocks')->where('business_id', $second)->count())->toBe(0);
});

it('answers 404 for a block of another business or an unknown one', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    $this->scopeQueriesTo($second);
    $foreign = (string) Str::uuid7();
    DB::table('schedule_blocks')->insert([
        ...scheduleBlockRow($second, null, '2026-11-10 10:00:00-06', '2026-11-10 12:00:00-06'),
        'id' => $foreign,
    ]);

    $this->scopeQueriesTo(null);
    blockOwner();

    $unknown = '019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f70';
    $foreignEdit = patchJson(BLOCKS_ENDPOINT.'/'.$foreign, ['motivo' => 'Robado']);
    $missingEdit = patchJson(BLOCKS_ENDPOINT.'/'.$unknown, ['motivo' => 'Robado']);
    $foreignDelete = deleteJson(BLOCKS_ENDPOINT.'/'.$foreign);

    expect($foreignEdit->status())->toBe(404)
        ->and($missingEdit->status())->toBe(404)
        ->and($foreignEdit->json('detail'))->toBe($missingEdit->json('detail'))
        ->and($foreignDelete->status())->toBe(404);

    $this->scopeQueriesTo($second);

    expect(DB::table('schedule_blocks')->where('id', $foreign)->count())->toBe(1);
});

it('forbids the staff and the customer, and refuses a request without a session', function (): void {
    TwoBusinesses::actAs(TwoBusinesses::ADVISOR);
    $asStaff = getJson(BLOCKS_ENDPOINT.'?desde=2026-11-12T00:00:00-06:00&hasta=2026-11-13T00:00:00-06:00');

    TwoBusinesses::actAs(TwoBusinesses::CUSTOMER);
    $asCustomer = getJson(BLOCKS_ENDPOINT.'?desde=2026-11-12T00:00:00-06:00&hasta=2026-11-13T00:00:00-06:00');

    Auth::forgetGuards();
    $asVisitor = getJson(BLOCKS_ENDPOINT.'?desde=2026-11-12T00:00:00-06:00&hasta=2026-11-13T00:00:00-06:00');

    expect($asStaff->status())->toBe(403)
        ->and($asCustomer->status())->toBe(403)
        ->and($asVisitor->status())->toBe(401);
});

it('never writes a block in another business', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    blockOwner();

    postJson(BLOCKS_ENDPOINT, [
        'inicia_en' => '2026-11-10T10:00:00-06:00',
        'termina_en' => '2026-11-10T12:00:00-06:00',
        'motivo' => 'Inventario',
        'business_id' => $second,
    ])->assertCreated();

    $this->scopeQueriesTo($first);
    expect(DB::table('schedule_blocks')->where('business_id', $first)->count())->toBe(1);

    $this->scopeQueriesTo($second);
    expect(DB::table('schedule_blocks')->where('business_id', $second)->count())->toBe(0);
});
