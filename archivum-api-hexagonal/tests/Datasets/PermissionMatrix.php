<?php

declare(strict_types=1);

use Tests\Support\Catalog\FakeImages;

/*
|--------------------------------------------------------------------------
| Permission matrix
|--------------------------------------------------------------------------
|
| Each row of section 9 of the specification with the endpoints that serve
| it: method, URI as the router names it, body, and the status each role of
| the "roles" dataset gets on a resource of ArchivumParfums (#105). The
| parameters of the URI are the ones of MatrixResources. An allowed role
| gets 2xx, or the 409 or 422 of a business rule after passing the checks
| of permission. A new endpoint of /panel or /cuenta joins its row, or
| PermissionMatrixTest fails.
|
*/

/**
 * Statuses of an endpoint only the owner reaches.
 *
 * @return array<string, int>
 */
function ownerOnly(int $ownerStatus): array
{
    return ['dueno' => $ownerStatus, 'colaborador' => 403, 'cliente' => 403, 'visitante' => 401];
}

/**
 * Statuses of an endpoint only a customer reaches, on their own resources.
 *
 * @return array<string, int>
 */
function customerOnly(int $customerStatus): array
{
    return ['dueno' => 403, 'colaborador' => 403, 'cliente' => $customerStatus, 'visitante' => 401];
}

/**
 * Statuses of an endpoint every signed-in role reaches.
 *
 * @return array<string, int>
 */
function anySignedIn(int $status): array
{
    return ['dueno' => $status, 'colaborador' => $status, 'cliente' => $status, 'visitante' => 401];
}

/**
 * The rows of section 9 that have endpoints today, by row, then by endpoint. A fifth element, when present, builds
 * the body of the attempt from another business, so it passes the validation and reaches the lookup.
 *
 * @return array<string, array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: array<string, int>, 4?: Closure(): array<string, mixed>}>>
 */
function permissionMatrixRows(): array
{
    return [
        'configurar tienda, horarios, servicios y personal' => [
            'ver el perfil' => ['GET', 'api/v1/panel/tienda', [], ownerOnly(200)],
            'editar el perfil' => ['PATCH', 'api/v1/panel/tienda', [], ownerOnly(200)],
            'subir el logo' => ['POST', 'api/v1/panel/tienda/logo', [], ownerOnly(422)],
        ],
        'catálogo, importación, botellas, proveedores y órdenes de compra' => [
            'listar perfumes' => ['GET', 'api/v1/panel/perfumes', [], ownerOnly(200)],
            'crear un perfume' => ['POST', 'api/v1/panel/perfumes', [], ownerOnly(422)],
            'ver un perfume' => ['GET', 'api/v1/panel/perfumes/{perfume}', [], ownerOnly(200)],
            'editar un perfume' => ['PATCH', 'api/v1/panel/perfumes/{perfume}', [], ownerOnly(200)],
            'subir la foto de un perfume' => ['POST', 'api/v1/panel/perfumes/{perfume}/foto', [], ownerOnly(422), fn (): array => ['foto' => FakeImages::png()]],
            'listar presentaciones' => ['GET', 'api/v1/panel/perfumes/{perfume}/presentaciones', [], ownerOnly(200)],
            'crear una presentación' => ['POST', 'api/v1/panel/perfumes/{perfume}/presentaciones', ['ml' => 37], ownerOnly(201)],
            'editar una presentación' => ['PATCH', 'api/v1/panel/perfumes/{perfume}/presentaciones/{presentacion}', [], ownerOnly(200)],
            'listar botellas' => ['GET', 'api/v1/panel/botellas', [], ownerOnly(200)],
            'abrir una botella' => ['POST', 'api/v1/panel/botellas', [], ownerOnly(422)],
            'ver una botella' => ['GET', 'api/v1/panel/botellas/{botella}', [], ownerOnly(200)],
            'editar una botella' => ['PATCH', 'api/v1/panel/botellas/{botella}', [], ownerOnly(200)],
            'ver los movimientos de una botella' => ['GET', 'api/v1/panel/botellas/{botella}/movimientos', [], ownerOnly(200)],
            'ajustar una botella' => ['POST', 'api/v1/panel/botellas/{botella}/ajustes', ['ml' => -1, 'motivo' => 'Prueba de la matriz de permisos'], ownerOnly(201)],
        ],
        'pedidos' => [
            'listar los pedidos de la tienda' => ['GET', 'api/v1/panel/pedidos', [], ownerOnly(200)],
            'ver un pedido de la tienda' => ['GET', 'api/v1/panel/pedidos/{pedido}', [], ownerOnly(200)],
            'preparar los decants' => ['POST', 'api/v1/panel/pedidos/{pedido}/preparacion', [], ownerOnly(409)],
            'marcar listo' => ['POST', 'api/v1/panel/pedidos/{pedido}/listo', [], ownerOnly(409)],
            'entregar' => ['POST', 'api/v1/panel/pedidos/{pedido}/entrega', [], ownerOnly(409)],
            'marcar una línea no disponible' => ['POST', 'api/v1/panel/pedidos/{pedido}/lineas/{linea}/no-disponible', [], ownerOnly(409)],
            'crear un pedido propio' => ['POST', 'api/v1/tiendas/{tienda}/pedidos', [], customerOnly(422)],
            'listar mis pedidos' => ['GET', 'api/v1/cuenta/pedidos', [], customerOnly(200)],
            'ver mi pedido' => ['GET', 'api/v1/cuenta/pedidos/{pedido}', [], customerOnly(200)],
            'cancelar mi pedido' => ['POST', 'api/v1/cuenta/pedidos/{pedido}/cancelacion', [], customerOnly(200)],
            'pagar mi pedido' => ['POST', 'api/v1/cuenta/pedidos/{pedido}/pagos', [], customerOnly(409)],
            'ver mi pago' => ['GET', 'api/v1/cuenta/pagos/{pago}', [], customerOnly(200)],
        ],
        'registrar pagos en la entrega y reembolsos' => [
            'procesar los reembolsos de un pedido' => ['POST', 'api/v1/panel/pedidos/{pedido}/reembolsos', [], ownerOnly(200)],
        ],
        'cuenta propia (sección 8)' => [
            'ver la cuenta' => ['GET', 'api/v1/cuenta', [], anySignedIn(200)],
        ],
    ];
}

/**
 * The rows of the matrix with method, URI, body and the status of each role.
 *
 * @return array<string, array<string, array{string, string, array<string, mixed>, array<string, int>}>>
 */
function permissionMatrix(): array
{
    return array_map(
        fn (array $endpoints): array => array_map(fn (array $endpoint): array => array_slice($endpoint, 0, 4), $endpoints),
        permissionMatrixRows(),
    );
}

/**
 * Endpoints whose URI names a resource of ArchivumParfums, with the account of another business that tries them:
 * the owner of the second business on the panel and a customer of it on the account.
 *
 * @return array<string, array{string, string, array<string, mixed>|Closure(): array<string, mixed>, string}>
 */
function permissionMatrixFromAnotherBusiness(string $row): array
{
    $endpoints = [];

    foreach (permissionMatrixRows()[$row] as $name => $endpoint) {
        [$method, $uri, $payload] = $endpoint;
        $payload = $endpoint[4] ?? $payload;
        $names = preg_match_all('/\{(?!tienda\})\w+\}/', $uri);

        if ($names > 0) {
            $endpoints[$name] = [$method, $uri, $payload, str_starts_with($uri, 'api/v1/panel/') ? 'otra tienda' : 'cliente de otra tienda'];
        }
    }

    return $endpoints;
}

foreach (array_keys(permissionMatrix()) as $row) {
    dataset("matriz: {$row}", permissionMatrix()[$row]);

    if (permissionMatrixFromAnotherBusiness($row) !== []) {
        dataset("matriz desde otra tienda: {$row}", permissionMatrixFromAnotherBusiness($row));
    }
}
