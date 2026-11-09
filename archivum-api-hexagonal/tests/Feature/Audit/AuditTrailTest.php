<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Shared\Domain\Contracts\AuditTrail;
use Src\Shared\Domain\ValueObjects\AuditAction;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Http\Middleware\AssignRequestId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Accounts\DemoAccounts;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Security\MatrixResources;

use function Pest\Laravel\json;

pest()->use(RefreshDatabaseAsOwner::class);

const AUDITED_RESOURCE = '01920000-0000-7000-8000-000000000001';

/**
 * Send a request as a demo account.
 *
 * @param  array<string, mixed>  $body
 * @return TestResponse<JsonResponse>
 */
function auditedRequest(string $account, string $method, string $uri, array $body = []): TestResponse
{
    $headers = DemoAccounts::signIn(MatrixResources::ACCOUNTS[$account]);
    Auth::forgetGuards();

    return json($method, $uri, $body, $headers);
}

/**
 * The entries of a business, oldest first, read with that business pinned.
 *
 * @return list<stdClass>
 */
function auditEntries(string $business = MatrixResources::BUSINESS): array
{
    return app(BusinessTransaction::class)->run($business, fn (): array => array_values(DB::table('audit_logs')->orderBy('id')->get()->all()));
}

/** The identifier of the account of a demo email. */
function auditedAccount(string $account): string
{
    return (string) DB::table('users')->where('email', MatrixResources::ACCOUNTS[$account])->value('id');
}

/**
 * Expect exactly one entry with its data, and answer it.
 *
 * @param  TestResponse<JsonResponse>  $response
 */
function expectOneEntry(TestResponse $response, AuditAction $action, string $account, string $resourceType): stdClass
{
    $entries = auditEntries();

    expect($entries)->toHaveCount(1);
    $entry = $entries[0];

    expect($entry->action)->toBe($action->value)
        ->and($entry->business_id)->toBe(MatrixResources::BUSINESS)
        ->and($entry->actor_user_id)->toBe(auditedAccount($account))
        ->and($entry->resource_type)->toBe($resourceType)
        ->and($entry->request_id)->toBe($response->headers->get(AssignRequestId::HEADER))
        ->and($entry->created_at)->not->toBeNull();

    return $entry;
}

/**
 * The URI with the resources of MatrixResources in its parameters.
 *
 * @param  array<string, string>  $resources
 */
function auditedUri(string $uri, array $resources): string
{
    foreach ($resources as $name => $value) {
        $uri = str_replace("{{$name}}", $value, $uri);
    }

    return $uri;
}

/** Replace the trail with one that always fails, to prove the change does not survive it. */
function breakAuditTrail(): void
{
    app()->instance(AuditTrail::class, new class implements AuditTrail
    {
        public function record(BusinessId $businessId, AuditAction $action, string $resourceType, string $resourceId, ?array $before, ?array $after): void
        {
            throw new RuntimeException('The entry could not be written.');
        }
    });
}

it('leaves one entry when a person joins the staff, without the password', function () {
    MatrixResources::create();

    $response = auditedRequest('dueno', 'POST', 'api/v1/panel/personal', [
        'nombre' => 'Andrea Solano', 'correo' => 'andrea@personal.test', 'contrasena' => 'clave-inicial-del-dueno', 'rol' => 'colaborador',
    ])->assertCreated();

    $entry = expectOneEntry($response, AuditAction::MemberAdded, 'dueno', 'member');

    expect($entry->resource_id)->toBe($response->json('data.id'))
        ->and($entry->before)->toBeNull()
        ->and(json_decode((string) $entry->after, true))->toMatchArray(['role' => 'staff', 'is_active' => true])
        ->and((string) $entry->after)->not->toContain('clave-inicial-del-dueno');
});

it('leaves one entry when the role of a person changes', function () {
    $resources = MatrixResources::create();

    $response = auditedRequest('dueno', 'PATCH', "api/v1/panel/personal/{$resources['persona']}", ['rol' => 'dueno'])->assertOk();

    $entry = expectOneEntry($response, AuditAction::MemberRoleChanged, 'dueno', 'member');

    expect($entry->resource_id)->toBe($resources['persona'])
        ->and(json_decode((string) $entry->before, true))->toMatchArray(['role' => 'staff'])
        ->and(json_decode((string) $entry->after, true))->toMatchArray(['role' => 'owner']);
});

it('leaves one entry when a person is deactivated, even if the role changes too', function () {
    $resources = MatrixResources::create();

    $response = auditedRequest('dueno', 'PATCH', "api/v1/panel/personal/{$resources['persona']}", ['activo' => false, 'rol' => 'dueno'])->assertOk();

    $entry = expectOneEntry($response, AuditAction::MemberDeactivated, 'dueno', 'member');

    expect(json_decode((string) $entry->before, true))->toMatchArray(['is_active' => true])
        ->and(json_decode((string) $entry->after, true))->toMatchArray(['is_active' => false]);
});

it('leaves no entry when only the name or the appointments of a person change', function () {
    $resources = MatrixResources::create();

    auditedRequest('dueno', 'PATCH', "api/v1/panel/personal/{$resources['persona']}", ['nombre' => 'Otro Nombre', 'atiende_citas' => false])->assertOk();

    expect(auditEntries())->toBe([]);
});

it('leaves one entry when a bottle is adjusted, with the ml before and after and the reason', function () {
    $resources = MatrixResources::create();
    $before = (int) app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn (): mixed => DB::table('decant_bottles')->where('id', $resources['botella'])->value('remaining_ml'));

    $response = auditedRequest('dueno', 'POST', "api/v1/panel/botellas/{$resources['botella']}/ajustes", ['ml' => -1, 'motivo' => 'Derrame al abrir'])->assertCreated();

    $entry = expectOneEntry($response, AuditAction::BottleAdjusted, 'dueno', 'decant_bottle');

    expect($entry->resource_id)->toBe($resources['botella'])
        ->and(json_decode((string) $entry->before, true))->toBe(['remaining_ml' => $before])
        ->and(json_decode((string) $entry->after, true))->toEqual(['remaining_ml' => $before - 1, 'reason' => 'Derrame al abrir']);
});

it('leaves one entry when the manual price of a presentation changes', function () {
    $resources = MatrixResources::create();

    $response = auditedRequest('dueno', 'PATCH', "api/v1/panel/perfumes/{$resources['perfume']}/presentaciones/{$resources['presentacion']}", ['precio_manual' => 12345])->assertOk();

    $entry = expectOneEntry($response, AuditAction::ManualPriceChanged, 'dueno', 'perfume_variant');

    expect($entry->resource_id)->toBe($resources['presentacion'])
        ->and(json_decode((string) $entry->after, true))->toBe(['manual_price' => 12345]);
});

it('leaves one entry when a presentation is created with a manual price, and none without it', function () {
    $resources = MatrixResources::create();
    $uri = "api/v1/panel/perfumes/{$resources['perfume']}/presentaciones";

    auditedRequest('dueno', 'POST', $uri, ['ml' => 37])->assertCreated();
    expect(auditEntries())->toBe([]);

    $response = auditedRequest('dueno', 'POST', $uri, ['ml' => 38, 'precio_manual' => 9000])->assertCreated();

    $entry = expectOneEntry($response, AuditAction::ManualPriceChanged, 'dueno', 'perfume_variant');

    expect($entry->resource_id)->toBe($response->json('data.id'))
        ->and($entry->before)->toBeNull();
});

it('leaves no entry when a presentation changes without touching its manual price', function () {
    $resources = MatrixResources::create();
    $uri = "api/v1/panel/perfumes/{$resources['perfume']}/presentaciones/{$resources['presentacion']}";

    auditedRequest('dueno', 'PATCH', $uri, ['ml' => 37])->assertOk();

    expect(auditEntries())->toBe([]);
});

it('leaves one entry per refund, with the customer who cancelled as its author', function () {
    $resources = MatrixResources::create();

    $response = auditedRequest('cliente', 'POST', "api/v1/cuenta/pedidos/{$resources['pedido']}/cancelacion")->assertOk();

    $entry = expectOneEntry($response, AuditAction::RefundRequested, 'cliente', 'refund');
    $refund = app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn (): ?object => DB::table('refunds')->first(['id', 'amount', 'payment_id']));
    assert(is_object($refund));

    expect($entry->resource_id)->toBe($refund->id)
        ->and(json_decode((string) $entry->after, true))->toMatchArray(['amount' => $refund->amount, 'payment_id' => $refund->payment_id]);
});

it('does not keep the change when its entry fails', function (string $account, string $method, string $uri, array $body, Closure $unchanged) {
    $resources = MatrixResources::create();
    $before = $unchanged($resources);
    breakAuditTrail();

    auditedRequest($account, $method, auditedUri($uri, $resources), $body)->assertStatus(500);

    expect($unchanged($resources))->toEqual($before)
        ->and(auditEntries())->toBe([]);
})->with([
    'a new person' => ['dueno', 'POST', 'api/v1/panel/personal', ['nombre' => 'Andrea', 'correo' => 'andrea@personal.test', 'contrasena' => 'clave-inicial-del-dueno', 'rol' => 'colaborador'],
        fn (array $resources): mixed => DB::table('users')->where('email', 'andrea@personal.test')->count()],
    'a change of role' => ['dueno', 'PATCH', 'api/v1/panel/personal/{persona}', ['rol' => 'dueno'],
        fn (array $resources): mixed => app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn (): mixed => DB::table('members')->where('id', $resources['persona'])->value('role'))],
    'a bottle' => ['dueno', 'POST', 'api/v1/panel/botellas/{botella}/ajustes', ['ml' => -1, 'motivo' => 'Derrame'],
        fn (array $resources): mixed => app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn (): mixed => DB::table('decant_bottles')->where('id', $resources['botella'])->value('remaining_ml'))],
    'a manual price' => ['dueno', 'PATCH', 'api/v1/panel/perfumes/{perfume}/presentaciones/{presentacion}', ['precio_manual' => 12345],
        fn (array $resources): mixed => app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn (): mixed => DB::table('perfume_variants')->where('id', $resources['presentacion'])->value('sale_price_override'))],
    'a refund' => ['cliente', 'POST', 'api/v1/cuenta/pedidos/{pedido}/cancelacion', [],
        fn (array $resources): mixed => app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn (): mixed => [DB::table('refunds')->count(), DB::table('orders')->where('id', $resources['pedido'])->value('status')])],
]);

it('writes the system as the author when no account is signed in', function () {
    MatrixResources::create();
    Auth::forgetGuards();

    app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn () => app(AuditTrail::class)->record(
        new BusinessId(MatrixResources::BUSINESS), AuditAction::RefundRequested, 'refund', AUDITED_RESOURCE, null, ['amount' => 1],
    ));

    expect(auditEntries()[0]->actor_user_id)->toBeNull();
});

it('never stores passwords, tokens or phones, at any depth', function () {
    MatrixResources::create();

    app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn () => app(AuditTrail::class)->record(
        new BusinessId(MatrixResources::BUSINESS), AuditAction::MemberAdded, 'member', AUDITED_RESOURCE,
        ['password' => 'secreto-uno', 'datos' => ['telefono' => '+50688880099']],
        ['token' => 'secreto-dos', 'contrasena' => 'secreto-tres', 'phone' => '+50688880098', 'role' => 'staff'],
    ));

    $entry = auditEntries()[0];

    expect((string) $entry->before)->not->toContain('secreto-uno')
        ->and((string) $entry->before)->not->toContain('88880099')
        ->and(json_decode((string) $entry->after, true))->toBe(['role' => 'staff']);
});

it('shows a business only its own entries', function () {
    MatrixResources::create();

    app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn () => app(AuditTrail::class)->record(
        new BusinessId(MatrixResources::BUSINESS), AuditAction::RefundRequested, 'refund', AUDITED_RESOURCE, null, null,
    ));

    expect(auditEntries())->toHaveCount(1)
        ->and(auditEntries(MatrixResources::OTHER_BUSINESS))->toBe([])
        ->and(app(BusinessTransaction::class)->run(null, fn (): int => DB::table('audit_logs')->count()))->toBe(0);
});

it('refuses an entry for another business', function () {
    MatrixResources::create();

    expect(fn () => DB::transaction(fn () => app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn () => app(AuditTrail::class)->record(
        new BusinessId(MatrixResources::OTHER_BUSINESS), AuditAction::RefundRequested, 'refund', AUDITED_RESOURCE, null, null,
    ))))->toThrow(QueryException::class, 'row-level security');
});

it('forces row-level security with the same expression as the other tables', function () {
    $table = DB::selectOne("select relrowsecurity, relforcerowsecurity from pg_class where relname = 'audit_logs'");
    $expression = function (string $policy): array {
        $row = DB::selectOne('select qual, with_check from pg_policies where policyname = ?', [$policy]);
        assert($row instanceof stdClass);

        return [$row->qual, $row->with_check];
    };

    expect($table->relrowsecurity)->toBeTrue()
        ->and($table->relforcerowsecurity)->toBeTrue()
        ->and($expression('audit_logs_business_isolation'))->toBe($expression('members_business_isolation'));
});

it('lets the application role only insert and read', function () {
    $user = (string) DB::scalar('select current_user');
    $can = fn (string $privilege): bool => (bool) DB::scalar('select has_table_privilege(?, ?, ?)', [$user, 'audit_logs', $privilege]);

    expect($user)->toBe(config('database.connections.pgsql.username'))
        ->and($can('SELECT'))->toBeTrue()
        ->and($can('INSERT'))->toBeTrue()
        ->and($can('UPDATE'))->toBeFalse()
        ->and($can('DELETE'))->toBeFalse()
        ->and($can('TRUNCATE'))->toBeFalse();
});

it('makes UPDATE, DELETE and TRUNCATE fail for the application role', function (string $statement) {
    MatrixResources::create();

    expect(fn () => DB::transaction(fn () => app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn () => DB::statement($statement))))
        ->toThrow(QueryException::class, 'permission denied');
})->with([
    "UPDATE audit_logs SET action = 'x'",
    'DELETE FROM audit_logs',
    'TRUNCATE audit_logs',
]);

it('makes UPDATE, DELETE and TRUNCATE fail for the owner of the table too, through its trigger', function (string $statement) {
    $owner = DB::connection('pgsql_admin');

    expect(fn () => $owner->transaction(fn () => $owner->statement($statement)))
        ->toThrow(QueryException::class, 'audit_logs cannot be changed');
})->with([
    "UPDATE audit_logs SET action = 'x'",
    'DELETE FROM audit_logs',
    'TRUNCATE audit_logs',
]);

// The endpoint does not exist in this branch: #98 calls the trail with AuditAction::ManualPaymentRecorded in its use case.
/**
 * Leave the order of MatrixResources ready, as the owner finds it when the customer pays its balance on delivery.
 *
 * @param  array<string, string>  $resources
 */
function readyForDelivery(array $resources): void
{
    app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, fn (): int => DB::table('orders')->where('id', $resources['pedido'])->update(['status' => 'ready']));
}

it('leaves one entry for each manual payment, with its order, its method and its amount', function () {
    $resources = MatrixResources::create();
    readyForDelivery($resources);
    $uri = "api/v1/panel/pedidos/{$resources['pedido']}/pagos-manuales";

    $response = auditedRequest('dueno', 'POST', $uri, ['metodo' => 'efectivo', 'monto' => 5000, 'referencia' => 'Recibo 0001'])->assertCreated();

    $entry = expectOneEntry($response, AuditAction::ManualPaymentRecorded, 'dueno', 'payment');

    expect($entry->resource_id)->toBe($response->json('data.id'))
        ->and($entry->before)->toBeNull()
        ->and(json_decode((string) $entry->after, true))->toEqual(['order_id' => $resources['pedido'], 'method' => 'cash', 'amount' => 5000]);

    auditedRequest('dueno', 'POST', $uri, ['metodo' => 'sinpe', 'monto' => 2500, 'referencia' => 'SINPE 0002'])->assertCreated();

    expect(array_column(auditEntries(), 'action'))->toBe([AuditAction::ManualPaymentRecorded->value, AuditAction::ManualPaymentRecorded->value]);
});

it('does not keep a manual payment when its entry fails', function () {
    $resources = MatrixResources::create();
    readyForDelivery($resources);
    breakAuditTrail();

    auditedRequest('dueno', 'POST', "api/v1/panel/pedidos/{$resources['pedido']}/pagos-manuales", ['metodo' => 'sinpe', 'monto' => 7500, 'referencia' => 'SINPE 0001'])->assertStatus(500);

    app(BusinessTransaction::class)->run(MatrixResources::BUSINESS, function () use ($resources): void {
        expect(DB::table('payments')->where('order_id', $resources['pedido'])->where('gateway', 'manual')->count())->toBe(0)
            ->and((array) DB::table('orders')->where('id', $resources['pedido'])->first(['paid', 'balance']))->toEqual(['paid' => 7500, 'balance' => 7500]);
    });
    expect(auditEntries())->toBe([]);
});
