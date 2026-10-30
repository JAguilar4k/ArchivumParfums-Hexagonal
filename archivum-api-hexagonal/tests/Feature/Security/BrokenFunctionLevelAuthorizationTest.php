<?php

declare(strict_types=1);

use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Security\Attack;
use Tests\Support\Security\MatrixResources;

pest()->use(RefreshDatabaseAsOwner::class);

/*
 * OWASP API Security Top 10 (2023), API5: Broken Function Level Authorization. A staff member or a customer who calls a
 * function of the owner gets 403, whatever the body says, and nothing changes. The whole matrix is in
 * PermissionMatrixTest (#105); these are the attempts with the body manipulated to claim more.
 */

dataset('attackers', [
    'a staff member' => [MatrixResources::ACCOUNTS['colaborador']],
    'a customer' => [MatrixResources::ACCOUNTS['cliente']],
]);

dataset('functions of the owner', [
    'configure the business' => ['PATCH', 'api/v1/panel/tienda', ['nombre' => 'Tienda tomada']],
    'refund an order' => ['POST', 'api/v1/panel/pedidos/{pedido}/reembolsos', []],
    'mark an order as delivered' => ['POST', 'api/v1/panel/pedidos/{pedido}/entrega', []],
    'mark an order as ready' => ['POST', 'api/v1/panel/pedidos/{pedido}/listo', []],
    'create a perfume' => ['POST', 'api/v1/panel/perfumes', ['marca' => 'Marca', 'nombre' => 'Intruso']],
    'adjust a bottle' => ['POST', 'api/v1/panel/botellas/{botella}/ajustes', ['ml' => -1, 'motivo' => 'Intruso']],
]);

it('API5: answers 403 to a function of the owner and changes nothing', function (string $attacker, string $method, string $uri, array $body) {
    $resources = MatrixResources::create();
    $writtenBefore = Attack::rowsWritten();

    Attack::as($attacker, $method, Attack::uri($uri, $resources), $body)
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/prohibido');

    expect(Attack::rowsWritten())->toBe($writtenBefore);
})->with('attackers')->with('functions of the owner');

it('API5: does not let a body that claims a role open a function of the owner', function (string $attacker) {
    MatrixResources::create();
    $writtenBefore = Attack::rowsWritten();

    Attack::as($attacker, 'PATCH', 'api/v1/panel/tienda', ['rol' => 'dueno', 'role' => 'owner', 'abilities' => ['*'], 'nombre' => 'Tienda tomada'])
        ->assertForbidden();

    expect(Attack::rowsWritten())->toBe($writtenBefore);
})->with('attackers');

it('API5: answers 401 to a visitor on a function of the owner', function () {
    $resources = MatrixResources::create();

    Attack::as(null, 'POST', "api/v1/panel/pedidos/{$resources['pedido']}/entrega")->assertUnauthorized();
});

// The endpoint does not exist yet: its issue adds this attempt when it creates it.
it('API5: answers 403 when a staff member or a customer registers a manual payment')->todo('POST /api/v1/panel/pedidos/{pedido}/pagos-manuales');
