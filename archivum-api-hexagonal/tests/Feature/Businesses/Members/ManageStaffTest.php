<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

const STAFF_PASSWORD = 'clave-del-personal-1';

/**
 * The body of a new person of the staff.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newPerson(array $overrides = []): array
{
    return [
        'nombre' => 'Andrea Solano',
        'correo' => 'andrea@personal.test',
        'contrasena' => STAFF_PASSWORD,
        'rol' => 'colaborador',
        'atiende_citas' => true,
        ...$overrides,
    ];
}

/** The row of a person, read in the transaction of the test with their business pinned, as its policy requires. */
function memberRow(StaffedBusiness $business, UserModel $user): MemberModel
{
    return app(BusinessTransaction::class)->run(
        $business->business->id,
        fn (): MemberModel => MemberModel::query()->where('user_id', $user->id)->sole(),
    );
}

/** Issue a real token for an account, as a sign-in leaves it stored. */
function tokenOf(UserModel $user, AccountRole $role): string
{
    return $user->createToken('Computadora', Ability::namesGrantedTo($role))->plainTextToken;
}

it('adds a person to the business of the session and points to them with Location', function () {
    $business = actingAsOwner();

    $response = postJson('/api/v1/panel/personal', newPerson());

    $response->assertCreated()
        ->assertJsonPath('data.nombre', 'Andrea Solano')
        ->assertJsonPath('data.correo', 'andrea@personal.test')
        ->assertJsonPath('data.rol', 'colaborador')
        ->assertJsonPath('data.atiende_citas', true)
        ->assertJsonPath('data.activo', true)
        ->assertJsonMissingPath('data.contrasena');
    $response->assertHeader('Location', route('businesses.members.show', ['persona' => $response->json('data.id')]));

    expect(MemberModel::query()->whereKey($response->json('data.id'))->sole()->business_id)->toBe($business->business->id);
});

it('lets the person added sign in with the role given', function () {
    actingAsOwner();
    postJson('/api/v1/panel/personal', newPerson())->assertCreated();
    Auth::forgetGuards();

    postJson('/api/v1/sesiones', ['correo' => 'andrea@personal.test', 'contrasena' => STAFF_PASSWORD, 'dispositivo' => 'Teléfono'])
        ->assertCreated();

    expect(PersonalAccessToken::query()->latest('id')->firstOrFail()->abilities)->toBe(Ability::namesGrantedTo(AccountRole::Staff));
});

it('ignores a business sent in the body when it adds a person', function () {
    $mine = actingAsOwner();
    $other = StaffedBusiness::create();

    $id = postJson('/api/v1/panel/personal', newPerson(['business_id' => $other->business->id, 'tienda' => $other->business->id]))
        ->assertCreated()
        ->json('data.id');

    expect(MemberModel::query()->whereKey($id)->sole()->business_id)->toBe($mine->business->id);
});

it('lists and shows the staff of the business with their role, whether they attend and whether they are active', function () {
    $business = actingAsOwner();
    $staff = memberRow($business, $business->staff);

    getJson('/api/v1/panel/personal')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['correo' => $business->owner->email, 'rol' => 'dueno', 'atiende_citas' => true, 'activo' => true]);

    getJson("/api/v1/panel/personal/{$staff->id}")
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $staff->id,
            'nombre' => $business->staff->name,
            'correo' => $business->staff->email,
            'rol' => 'colaborador',
            'atiende_citas' => true,
            'activo' => true,
        ]]);
});

it('changes the name, the role and whether the person attends appointments', function () {
    $business = actingAsOwner();
    $staff = memberRow($business, $business->staff);

    patchJson("/api/v1/panel/personal/{$staff->id}", ['nombre' => 'Otro Nombre', 'rol' => 'dueno', 'atiende_citas' => false])
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Otro Nombre')
        ->assertJsonPath('data.rol', 'dueno')
        ->assertJsonPath('data.atiende_citas', false);

    expect($business->staff->refresh()->name)->toBe('Otro Nombre');
});

it('does not change the email with a PATCH', function () {
    $business = actingAsOwner();
    $staff = memberRow($business, $business->staff);

    patchJson("/api/v1/panel/personal/{$staff->id}", ['correo' => 'nuevo@personal.test'])->assertOk();

    expect($business->staff->refresh()->email)->not->toBe('nuevo@personal.test');
});

it('deactivates a person without deleting them, and they can no longer sign in', function () {
    $business = actingAsOwner();
    $staff = memberRow($business, $business->staff);
    $business->staff->forceFill(['password' => bcrypt(STAFF_PASSWORD)])->save();

    patchJson("/api/v1/panel/personal/{$staff->id}", ['activo' => false])
        ->assertOk()
        ->assertJsonPath('data.activo', false);
    getJson("/api/v1/panel/personal/{$staff->id}")->assertOk()->assertJsonPath('data.activo', false);
    Auth::forgetGuards();

    postJson('/api/v1/sesiones', ['correo' => $business->staff->email, 'contrasena' => STAFF_PASSWORD, 'dispositivo' => 'Teléfono'])
        ->assertUnauthorized()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/credenciales-invalidas');
});

it('lets a deactivated person back in when the owner activates them again', function () {
    $business = actingAsOwner();
    $staff = memberRow($business, $business->staff);
    $business->staff->forceFill(['password' => bcrypt(STAFF_PASSWORD)])->save();
    patchJson("/api/v1/panel/personal/{$staff->id}", ['activo' => false])->assertOk();

    patchJson("/api/v1/panel/personal/{$staff->id}", ['activo' => true])->assertOk()->assertJsonPath('data.activo', true);
    Auth::forgetGuards();

    postJson('/api/v1/sesiones', ['correo' => $business->staff->email, 'contrasena' => STAFF_PASSWORD, 'dispositivo' => 'Teléfono'])
        ->assertCreated();
});

it('resolves no business for a deactivated person', function () {
    $business = StaffedBusiness::create();
    app(BusinessTransaction::class)->run($business->business->id, fn () => DB::table('members')->where('user_id', $business->staff->id)->update(['is_active' => false]));
    Sanctum::actingAs($business->staff, ['*']);

    getJson('/api/v1/panel/tienda')->assertForbidden();
});

it('answers 422 in correo, without naming the business, for an email already on a staff', function () {
    actingAsOwner();
    $other = StaffedBusiness::create();

    $response = postJson('/api/v1/panel/personal', newPerson(['correo' => $other->staff->email]));

    $response->assertUnprocessable()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->assertJsonValidationErrors(['correo'], 'errores');
    expect($response->getContent())->not->toContain($other->business->name)
        ->and($response->getContent())->not->toContain($other->business->slug);
});

it('refuses the email of an existing customer account instead of turning it into staff', function () {
    actingAsOwner();
    $customer = StaffedBusiness::create()->customer;

    postJson('/api/v1/panel/personal', newPerson(['correo' => $customer->email]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['correo'], 'errores');

    expect(DB::select('select * from fn_member_business(?)', [$customer->id]))->toBe([]);
});

it('revokes the tokens of a person whose role changes', function () {
    $business = actingAsOwner();
    $staff = memberRow($business, $business->staff);
    tokenOf($business->staff, AccountRole::Staff);

    patchJson("/api/v1/panel/personal/{$staff->id}", ['rol' => 'dueno'])->assertOk();

    expect($business->staff->tokens()->count())->toBe(0);
});

it('revokes the tokens of a person who is deactivated, so an old token stops working', function () {
    $business = StaffedBusiness::create();
    $staff = memberRow($business, $business->staff);
    $old = tokenOf($business->staff, AccountRole::Staff);
    actingAsOwner($business);

    patchJson("/api/v1/panel/personal/{$staff->id}", ['activo' => false])->assertOk();

    expect($business->staff->tokens()->count())->toBe(0);
    Auth::forgetGuards();
    getJson('/api/v1/cuenta', ['Authorization' => "Bearer {$old}"])->assertUnauthorized();
});

it('keeps the tokens of a person when only the name or the appointments change', function () {
    $business = actingAsOwner();
    $staff = memberRow($business, $business->staff);
    tokenOf($business->staff, AccountRole::Staff);

    patchJson("/api/v1/panel/personal/{$staff->id}", ['nombre' => 'Otro Nombre', 'atiende_citas' => false])->assertOk();

    expect($business->staff->tokens()->count())->toBe(1);
});

it('answers 403 to a staff member and a customer on every operation', function (string $role) {
    $business = actingAsRole($role);
    $staff = memberRow($business, $business->staff);

    getJson('/api/v1/panel/personal')->assertForbidden();
    getJson("/api/v1/panel/personal/{$staff->id}")->assertForbidden();
    postJson('/api/v1/panel/personal', newPerson())->assertForbidden();
    patchJson("/api/v1/panel/personal/{$staff->id}", ['rol' => 'dueno'])->assertForbidden();

    expect(memberRow($business, $business->staff)->role)->toBe('staff');
})->with(['colaborador', 'cliente']);

it('answers 404 for a person of another business and changes nothing', function () {
    actingAsOwner();
    $other = StaffedBusiness::create();
    $foreign = memberRow($other, $other->staff);

    getJson("/api/v1/panel/personal/{$foreign->id}")->assertNotFound();
    patchJson("/api/v1/panel/personal/{$foreign->id}", ['activo' => false, 'nombre' => 'Intruso'])->assertNotFound();

    expect(memberRow($other, $other->staff)->is_active)->toBeTrue()
        ->and($other->staff->refresh()->name)->not->toBe('Intruso');
});

it('only lists the staff of the business of the session', function () {
    $mine = actingAsOwner();
    $other = StaffedBusiness::create();

    /** @var list<array{correo: string}> $people */
    $people = getJson('/api/v1/panel/personal')->assertOk()->json('data');
    $emails = array_column($people, 'correo');

    expect($emails)->toContain($mine->owner->email)
        ->and($emails)->not->toContain($other->owner->email)
        ->and($emails)->not->toContain($other->staff->email);
});

it('answers 409 when the change would leave the business without an active owner', function (array $change) {
    $business = actingAsOwner();
    $owner = memberRow($business, $business->owner);

    patchJson("/api/v1/panel/personal/{$owner->id}", $change)
        ->assertConflict()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/conflicto')
        ->assertJsonPath('detail', __('businesses.last_active_owner'));

    expect(memberRow($business, $business->owner)->is_active)->toBeTrue()
        ->and(memberRow($business, $business->owner)->role)->toBe('owner');
})->with([
    'deactivating the last owner' => [['activo' => false]],
    'taking the role from the last owner' => [['rol' => 'colaborador']],
]);

it('lets an owner leave when another active owner remains', function () {
    $business = actingAsOwner();
    $owner = memberRow($business, $business->owner);
    $staff = memberRow($business, $business->staff);
    patchJson("/api/v1/panel/personal/{$staff->id}", ['rol' => 'dueno'])->assertOk();

    patchJson("/api/v1/panel/personal/{$owner->id}", ['activo' => false])->assertOk();

    expect(memberRow($business, $business->owner)->is_active)->toBeFalse();
});

it('does not offer to delete a person: section 8 asks for it, this issue only deactivates', function () {
    $business = actingAsOwner();
    $staff = memberRow($business, $business->staff);

    deleteJson("/api/v1/panel/personal/{$staff->id}")->assertStatus(405);
});

it('validates the fields of a new person and of a change', function () {
    $business = actingAsOwner();
    $staff = memberRow($business, $business->staff);

    postJson('/api/v1/panel/personal', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nombre', 'correo', 'contrasena', 'rol'], 'errores');
    patchJson("/api/v1/panel/personal/{$staff->id}", ['rol' => 'cliente', 'activo' => 'quizas'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rol', 'activo'], 'errores');
});
