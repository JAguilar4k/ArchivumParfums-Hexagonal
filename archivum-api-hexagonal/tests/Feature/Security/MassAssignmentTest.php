<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Assert;
use Src\Accounts\Users\Application\DTOs\RegisterAccountCommand;
use Src\Sales\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Security\Attack;
use Tests\Support\Security\MatrixResources;

use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

/*
 * OWASP API Security Top 10 (2023), API3: Broken Object Property Level Authorization, the mass assignment half. The
 * business, the status, the amounts and the role that arrive in a body are ignored: a Form Request only lets through
 * what it validates, and the DTO of the use case has no field for them.
 */

it('API3: creates an order in the business of the URL with the amounts of the server, whatever the body claims', function () {
    $resources = MatrixResources::create();
    $price = (int) app(BusinessTransaction::class)->run(
        MatrixResources::BUSINESS,
        fn (): mixed => DB::table('perfume_variants')->where('id', $resources['presentacion'])->value('sale_price'),
    );

    $response = Attack::as(MatrixResources::ACCOUNTS['cliente'], 'POST', 'api/v1/tiendas/archivumparfums/pedidos', [
        'business_id' => MatrixResources::OTHER_BUSINESS,
        'tienda' => MatrixResources::OTHER_BUSINESS,
        'estado' => 'entregado',
        'status' => 'delivered',
        'subtotal' => 1,
        'pagado' => 999999,
        'saldo' => 0,
        'due_now' => 0,
        'cliente_id' => '01920000-0000-7000-8000-000000000000',
        'lineas' => [['presentacion_id' => $resources['presentacion'], 'cantidad' => 2, 'precio_unitario' => 1, 'unit_price' => 1, 'estado' => 'received']],
    ])->assertCreated();

    $order = app(BusinessTransaction::class)->run(
        MatrixResources::BUSINESS,
        fn (): ?object => DB::table('orders')->where('id', $response->json('data.id'))->first(),
    );

    assert(is_object($order));

    expect($order->business_id)->toBe(MatrixResources::BUSINESS)
        ->and($order->status)->toBe('pending_payment')
        ->and($order->subtotal)->toBe($price * 2)
        ->and($order->paid)->toBe(0)
        ->and($response->json('data.lineas.0.precio_unitario'))->toBe($price);
});

it('API3: ignores the identifier, the slug and the business sent to the profile of the business', function () {
    MatrixResources::create();

    Attack::as(MatrixResources::ACCOUNTS['dueno'], 'PATCH', 'api/v1/panel/tienda', [
        'id' => MatrixResources::OTHER_BUSINESS,
        'business_id' => MatrixResources::OTHER_BUSINESS,
        'slug' => 'tienda-tomada',
        'nombre' => 'Nombre nuevo',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', MatrixResources::BUSINESS)
        ->assertJsonPath('data.slug', 'archivumparfums')
        ->assertJsonPath('data.nombre', 'Nombre nuevo');
});

it('API3: ignores the business and the id sent when a perfume changes', function () {
    $resources = MatrixResources::create();

    Attack::as(MatrixResources::ACCOUNTS['dueno'], 'PATCH', "api/v1/panel/perfumes/{$resources['perfume']}", [
        'id' => '01920000-0000-7000-8000-000000000000',
        'business_id' => MatrixResources::OTHER_BUSINESS,
    ])->assertOk()->assertJsonPath('data.id', $resources['perfume']);

    $business = app(BusinessTransaction::class)->run(
        MatrixResources::BUSINESS,
        fn (): mixed => DB::table('perfumes')->where('id', $resources['perfume'])->value('business_id'),
    );

    expect($business)->toBe(MatrixResources::BUSINESS);
});

it('API3: ignores the remaining ml and the status sent when a bottle changes', function () {
    $resources = MatrixResources::create();
    $read = fn (): ?object => app(BusinessTransaction::class)->run(
        MatrixResources::BUSINESS,
        fn (): ?object => DB::table('decant_bottles')->where('id', $resources['botella'])->first(['remaining_ml', 'reserved_ml', 'status']),
    );
    $before = $read();

    Attack::as(MatrixResources::ACCOUNTS['dueno'], 'PATCH', "api/v1/panel/botellas/{$resources['botella']}", [
        'ml_restantes' => 9999,
        'remaining_ml' => 9999,
        'ml_reservados' => 0,
        'estado' => 'vacia',
        'status' => 'empty',
    ])->assertOk();

    expect($read())->toEqual($before);
});

it('API3: opens every new account as a customer, whatever the body claims', function () {
    if (! Route::has('accounts.users.store')) {
        Assert::markTestSkipped('POST /api/v1/cuentas llega con #101.');
    }

    MatrixResources::create();

    postJson('/api/v1/cuentas', [
        'nombre' => 'Nueva Cuenta',
        'correo' => 'nueva@clientes.test',
        'contrasena' => 'una-clave-bien-larga',
        'rol' => 'dueno',
        'role' => 'owner',
        'business_id' => MatrixResources::BUSINESS,
        'tienda' => MatrixResources::BUSINESS,
        'is_admin' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.rol', 'cliente');

    $account = DB::table('users')->where('email', 'nueva@clientes.test')->value('id');

    expect(DB::select('select * from fn_member_business(?)', [$account]))->toBe([]);
});

it('API3: leaves the fields of business, status and amounts out of the commands of the client', function (string $command) {
    assert(class_exists($command));
    $properties = array_map(fn (ReflectionProperty $property): string => strtolower($property->getName()), new ReflectionClass($command)->getProperties());

    expect(array_filter($properties, fn (string $name): bool => preg_match('/business|status|amount|price|subtotal|paid|balance|role/', $name) === 1))->toBe([]);
})->with([PlaceOrderCommand::class, RegisterAccountCommand::class]);
