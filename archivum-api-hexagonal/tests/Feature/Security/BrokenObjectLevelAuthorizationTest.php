<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Security\Attack;
use Tests\Support\Security\MatrixResources;

pest()->use(RefreshDatabaseAsOwner::class);

/*
 * OWASP API Security Top 10 (2023), API1: Broken Object Level Authorization. A resource named in the URL that belongs
 * to another customer or to another business answers 404 and changes nothing. An identifier in the body is a different
 * case: it answers 422 in that field, and the last tests tell the two apart.
 */

/** The status of the order of MatrixResources, read with its business pinned. */
function statusOfTheOrder(string $order): string
{
    return (string) app(BusinessTransaction::class)->run(
        MatrixResources::BUSINESS,
        fn (): mixed => DB::table('orders')->where('id', $order)->value('status'),
    );
}

dataset('resources of a customer', [
    'read an order' => ['GET', 'api/v1/cuenta/pedidos/{pedido}'],
    'cancel an order' => ['POST', 'api/v1/cuenta/pedidos/{pedido}/cancelacion'],
    'pay an order' => ['POST', 'api/v1/cuenta/pedidos/{pedido}/pagos'],
    'read a payment' => ['GET', 'api/v1/cuenta/pagos/{pago}'],
]);

it('API1: answers 404 to another customer of the same business and changes nothing', function (string $method, string $uri) {
    $resources = MatrixResources::create();
    $writtenBefore = Attack::rowsWritten();

    Attack::as(Attack::OTHER_CUSTOMER, $method, strtr($uri, ['{pedido}' => $resources['pedido'], '{pago}' => $resources['pago']]))
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');

    expect(Attack::rowsWritten())->toBe($writtenBefore)
        ->and(statusOfTheOrder($resources['pedido']))->toBe('confirmed');
})->with('resources of a customer');

it('API1: answers 404 to a customer of another business and changes nothing', function (string $method, string $uri) {
    $resources = MatrixResources::create();
    $writtenBefore = Attack::rowsWritten();

    Attack::as(MatrixResources::ACCOUNTS['cliente de otra tienda'], $method, strtr($uri, ['{pedido}' => $resources['pedido'], '{pago}' => $resources['pago']]))
        ->assertNotFound();

    expect(Attack::rowsWritten())->toBe($writtenBefore)
        ->and(statusOfTheOrder($resources['pedido']))->toBe('confirmed');
})->with('resources of a customer');

it('API1: answers 404 to the owner of another business and changes nothing', function (string $method, string $uri, array $body) {
    $resources = MatrixResources::create();
    $writtenBefore = Attack::rowsWritten();

    Attack::as(MatrixResources::ACCOUNTS['otra tienda'], $method, Attack::uri($uri, $resources), $body)
        ->assertNotFound();

    expect(Attack::rowsWritten())->toBe($writtenBefore)
        ->and(statusOfTheOrder($resources['pedido']))->toBe('confirmed');
})->with([
    'read an order' => ['GET', 'api/v1/panel/pedidos/{pedido}', []],
    'deliver an order' => ['POST', 'api/v1/panel/pedidos/{pedido}/entrega', []],
    'refund an order' => ['POST', 'api/v1/panel/pedidos/{pedido}/reembolsos', []],
    'edit a perfume' => ['PATCH', 'api/v1/panel/perfumes/{perfume}', ['nombre' => 'Intruso']],
    'edit a presentation' => ['PATCH', 'api/v1/panel/perfumes/{perfume}/presentaciones/{presentacion}', ['precio_manual' => 1]],
    'edit a bottle' => ['PATCH', 'api/v1/panel/botellas/{botella}', ['notas' => 'Intruso']],
]);

it('API1: answers the same 404 for a resource of another business as for one that does not exist', function () {
    $resources = MatrixResources::create();
    $missing = '01920000-0000-7000-8000-000000000000';

    $foreign = Attack::as(MatrixResources::ACCOUNTS['cliente de otra tienda'], 'GET', "api/v1/cuenta/pedidos/{$resources['pedido']}")->assertNotFound();
    $absent = Attack::as(MatrixResources::ACCOUNTS['cliente de otra tienda'], 'GET', "api/v1/cuenta/pedidos/{$missing}")->assertNotFound();

    expect($foreign->json('detail'))->toBe($absent->json('detail'))
        ->and($foreign->json('type'))->toBe($absent->json('type'));
});

it('API1: answers 422 in the field, not 404, when a presentation of the body belongs to another business', function () {
    MatrixResources::create();
    $writtenBefore = Attack::rowsWritten();

    Attack::as(MatrixResources::ACCOUNTS['cliente'], 'POST', 'api/v1/tiendas/archivumparfums/pedidos', [
        'lineas' => [['presentacion_id' => MatrixResources::foreignPresentation(), 'cantidad' => 1]],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->assertJsonValidationErrors(['lineas.0.presentacion_id'], 'errores');

    expect(Attack::rowsWritten())->toBe($writtenBefore);
});

it('API1: answers 422 in the field, not 404, when a bottle of the body belongs to another business', function () {
    MatrixResources::create();
    $foreign = (string) app(BusinessTransaction::class)->run(
        MatrixResources::OTHER_BUSINESS,
        fn (): mixed => DB::table('decant_bottles')->where('business_id', MatrixResources::OTHER_BUSINESS)->orderBy('id')->value('id'),
    );
    expect($foreign)->not->toBe('');
    $writtenBefore = Attack::rowsWritten();

    Attack::as(MatrixResources::ACCOUNTS['cliente'], 'POST', 'api/v1/tiendas/archivumparfums/pedidos', [
        'lineas' => [['botella_id' => $foreign, 'ml' => 5]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lineas.0.botella_id'], 'errores');

    expect(Attack::rowsWritten())->toBe($writtenBefore);
});

it('API1: does not list the orders of another customer', function () {
    $resources = MatrixResources::create();

    $response = Attack::as(Attack::OTHER_CUSTOMER, 'GET', 'api/v1/cuenta/pedidos')->assertOk();

    expect((string) $response->getContent())->not->toContain($resources['pedido']);
});

// The appointments do not exist yet: their issues add these attempts when they create the endpoints.
it('API1: answers 404 when reading, moving or cancelling an appointment of another customer')->todo('#62, #65 y #66');
