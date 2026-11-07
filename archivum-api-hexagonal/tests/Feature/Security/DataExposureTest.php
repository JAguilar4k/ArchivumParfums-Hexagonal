<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Security\Attack;
use Tests\Support\Security\MatrixResources;

use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

/*
 * OWASP API Security Top 10 (2023), API3: Broken Object Property Level Authorization, the excessive data exposure half.
 * Each response leaves a Resource that lists its fields, so a new column never reaches the API by accident. These
 * tests read the responses as a client does, looking for what must never leave.
 */

/**
 * Every key of a decoded JSON document, at any depth.
 *
 * @return list<string>
 */
function jsonKeys(mixed $document): array
{
    if (! is_array($document)) {
        return [];
    }

    $keys = [];

    foreach ($document as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        array_push($keys, ...jsonKeys($value));
    }

    return $keys;
}

/** The test phones of the customers of the seeders. */
const CUSTOMER_PHONES = ['+50688880001', '+50688880002', '+50688880003', '+50688880004', '50688880001'];

/** Fields with secrets that no response of the API may carry. */
const SECRET_FIELDS = ['password', 'contrasena', 'password_hash', 'remember_token', 'secret', 'token_hash'];

it('API3: never answers a secret or the phone of a customer', function (?string $account, string $uri) {
    $resources = MatrixResources::create();
    $response = Attack::as($account === null ? null : MatrixResources::ACCOUNTS[$account], 'GET', strtr($uri, ['{pedido}' => $resources['pedido'], '{pago}' => $resources['pago'], '{perfume}' => $resources['perfume']]))->assertOk();
    $body = (string) $response->getContent();

    expect(array_intersect(jsonKeys($response->json()), SECRET_FIELDS))->toBe([])
        ->and(array_intersect(jsonKeys($response->json()), ['phone', 'telefono']))->toBe([])
        ->and($body)->not->toContain('$2y$');

    foreach (CUSTOMER_PHONES as $phone) {
        expect($body)->not->toContain($phone);
    }
})->with([
    'the account of a customer' => ['cliente', 'api/v1/cuenta'],
    'the account of the owner' => ['dueno', 'api/v1/cuenta'],
    'the orders of a customer' => ['cliente', 'api/v1/cuenta/pedidos'],
    'an order of a customer' => ['cliente', 'api/v1/cuenta/pedidos/{pedido}'],
    'a payment of a customer' => ['cliente', 'api/v1/cuenta/pagos/{pago}'],
    'the orders of the business' => ['dueno', 'api/v1/panel/pedidos'],
    'an order of the business' => ['dueno', 'api/v1/panel/pedidos/{pedido}'],
    'the profile of the business' => ['dueno', 'api/v1/panel/tienda'],
    'the public profile' => [null, 'api/v1/tiendas/archivumparfums'],
    'the showcase' => [null, 'api/v1/tiendas/archivumparfums/perfumes'],
    'a perfume of the showcase' => [null, 'api/v1/tiendas/archivumparfums/perfumes/{perfume}'],
]);

it('API3: keeps the settings, the staff and the costs of the business out of the showcase', function (string $uri) {
    $resources = MatrixResources::create();
    $response = Attack::as(null, 'GET', strtr($uri, ['{perfume}' => $resources['perfume']]))->assertOk();

    expect(array_intersect(jsonKeys($response->json()), [
        'ajustes', 'settings', 'margen_botella', 'tipo_cambio_usd', 'personal', 'members', 'staff', 'dueno', 'correo', 'email', 'costo', 'cost', 'business_id',
    ]))->toBe([]);
    expect((string) $response->getContent())->not->toContain('@archivumparfums.test');
})->with([
    'the public profile' => ['api/v1/tiendas/archivumparfums'],
    'the showcase' => ['api/v1/tiendas/archivumparfums/perfumes'],
    'a perfume of the showcase' => ['api/v1/tiendas/archivumparfums/perfumes/{perfume}'],
]);

it('API3: answers the token only once, when the session starts', function () {
    MatrixResources::create();
    $sign = postJson('/api/v1/sesiones', ['correo' => MatrixResources::ACCOUNTS['cliente'], 'contrasena' => 'clave-de-demostracion', 'dispositivo' => 'Prueba'])->assertCreated();
    $token = (string) $sign->json('data.token');

    $account = Attack::as(MatrixResources::ACCOUNTS['cliente'], 'GET', 'api/v1/cuenta')->assertOk();

    expect(array_intersect(jsonKeys($account->json()), ['token', 'tokens', 'abilities']))->toBe([])
        ->and((string) $account->getContent())->not->toContain($token);
});

it('API3: does not repeat the password in a failed sign-in or validation', function () {
    MatrixResources::create();
    $password = 'clave-secreta-de-la-prueba';

    $failed = postJson('/api/v1/sesiones', ['correo' => MatrixResources::ACCOUNTS['cliente'], 'contrasena' => $password, 'dispositivo' => 'Prueba'])->assertUnauthorized();
    $invalid = postJson('/api/v1/sesiones', ['correo' => 'no-es-un-correo', 'contrasena' => $password])->assertUnprocessable();

    expect((string) $failed->getContent())->not->toContain($password)
        ->and((string) $invalid->getContent())->not->toContain($password);
});

it('API3: answers from a Resource, except the controllers that list their fields by hand', function () {
    $controllers = Finder::create()->files()->in(base_path('src'))->path('Infrastructure/Http/Controllers')->name('*.php');

    $raw = [];

    foreach ($controllers as $controller) {
        $source = $controller->getContents();

        if (preg_match('/response\(\)->json\(|new JsonResponse\(|->toArray\(\)/', $source) === 1) {
            $raw[] = $controller->getFilename();
        }
    }

    // They list their fields by hand, without a whole model: see docs/security.md. Do not add another one to this list.
    expect($raw)->toEqualCanonicalizing([
        'BusinessLogoController.php',
        'PerfumePhotoController.php',
        'DecantMovementController.php',
        'PaymentController.php',
        'ApiIndexController.php',
    ]);
});
