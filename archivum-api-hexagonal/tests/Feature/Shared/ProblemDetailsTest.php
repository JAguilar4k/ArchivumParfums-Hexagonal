<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Src\Shared\Domain\Exceptions\AuthenticationFailedException;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\Exceptions\NotFoundException;
use Src\Shared\Infrastructure\Http\Problem\ProblemType;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

const PROBLEM_CONTENT_TYPE = 'application/problem+json';

/** Resource of the current business, of another business and one that does not exist. */
const OWN_RESOURCE_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d01';
const FOREIGN_RESOURCE_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d02';
const MISSING_RESOURCE_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d03';

beforeEach(function () {
    Route::prefix('api/v1/pruebas')->middleware('api')->group(function (): void {
        Route::get('fallo', fn () => throw new RuntimeException('SQLSTATE[42P01] at /var/www/secret.php'));

        Route::get('dominio', fn () => throw new InvalidValueException('shared.invalid_identifier'));

        Route::get('conflicto', fn () => throw new class('validation.unique', ['attribute' => 'slug']) extends DomainException {});

        Route::post('validacion', fn (Request $request) => $request->validate(['precio' => 'required|integer']));

        Route::post('credenciales', fn () => throw new class('problems.credenciales-invalidas.detail') extends AuthenticationFailedException {});

        Route::get('estado/{status}', fn (int $status) => throw new HttpException($status));

        Route::get('limite', fn () => throw new TooManyRequestsHttpException(30));

        Route::get('protegida', fn () => 'ok')->middleware('auth');

        // Only the resources of the current business are visible, whatever the identifier says.
        Route::get('recursos/{recurso}', function (string $recurso) {
            if ($recurso !== OWN_RESOURCE_ID) {
                throw new class('problems.no-encontrado.detail') extends NotFoundException {};
            }

            return response()->noContent();
        });

        Route::post('recursos', function (Request $request) {
            $request->validate([
                'recurso_id' => ['required', 'uuid', function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== OWN_RESOURCE_ID) {
                        $fail('validation.exists')->translate();
                    }
                }],
            ]);

            return response()->noContent();
        });
    });
});

it('answers an unknown route with a complete not found problem', function () {
    $response = getJson('/api/v1/desconocido');

    $response->assertNotFound()
        ->assertHeader('Content-Type', PROBLEM_CONTENT_TYPE)
        ->assertExactJson([
            'type' => 'http://localhost/problemas/no-encontrado',
            'title' => 'Recurso no encontrado',
            'status' => 404,
            'detail' => 'El recurso solicitado no existe.',
            'instance' => 'urn:uuid:'.$response->headers->get('X-Request-Id'),
        ]);
});

it('repeats the incoming request identifier in the instance member', function () {
    $requestId = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d11';

    getJson('/api/v1/desconocido', ['X-Request-Id' => $requestId])
        ->assertJsonPath('instance', "urn:uuid:{$requestId}");
});

it('lists the validation errors per field in spanish', function () {
    postJson('/api/v1/pruebas/validacion', ['precio' => 'caro'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', PROBLEM_CONTENT_TYPE)
        ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
        ->assertJsonPath('title', 'Datos inválidos')
        ->assertJsonPath('detail', 'Los datos enviados no son válidos.')
        ->assertJsonPath('errores.precio.0', 'El campo precio debe ser un número entero.');
});

it('answers an invalid value of the domain with its translated message', function () {
    getJson('/api/v1/pruebas/dominio')
        ->assertUnprocessable()
        ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
        ->assertJsonPath('detail', 'El identificador no es válido.');
});

it('answers any other domain exception as a conflict', function () {
    getJson('/api/v1/pruebas/conflicto')
        ->assertConflict()
        ->assertHeader('Content-Type', PROBLEM_CONTENT_TYPE)
        ->assertJsonPath('type', 'http://localhost/problemas/conflicto')
        ->assertJsonPath('title', 'Conflicto')
        ->assertJsonPath('status', 409)
        ->assertJsonPath('detail', 'El valor del campo slug ya está en uso.');
});

it('answers a failed authentication as invalid credentials', function () {
    postJson('/api/v1/pruebas/credenciales')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', PROBLEM_CONTENT_TYPE)
        ->assertJsonPath('type', 'http://localhost/problemas/credenciales-invalidas')
        ->assertJsonPath('title', 'Credenciales inválidas')
        ->assertJsonPath('detail', 'El correo o la contraseña no son correctos.');
});

it('answers a protected route without a session as unauthenticated', function (array $headers) {
    get('/api/v1/pruebas/protegida', $headers)
        ->assertUnauthorized()
        ->assertHeader('Content-Type', PROBLEM_CONTENT_TYPE)
        ->assertJsonPath('type', 'http://localhost/problemas/no-autenticado')
        ->assertJsonPath('detail', 'Debe iniciar sesión para realizar esta acción.');
})->with([
    'without an accept header' => [[]],
    'asking for json' => [['Accept' => 'application/json']],
]);

it('keeps the status of the framework http exceptions with their type', function (int $status, string $type, string $title) {
    getJson("/api/v1/pruebas/estado/{$status}")
        ->assertStatus($status)
        ->assertHeader('Content-Type', PROBLEM_CONTENT_TYPE)
        ->assertJsonPath('type', "http://localhost/problemas/{$type}")
        ->assertJsonPath('title', $title)
        ->assertJsonPath('status', $status);
})->with([
    [400, 'solicitud-invalida', 'Solicitud inválida'],
    [401, 'no-autenticado', 'No autenticado'],
    [403, 'prohibido', 'Acción no permitida'],
    [404, 'no-encontrado', 'Recurso no encontrado'],
    [405, 'metodo-no-permitido', 'Método no permitido'],
    [409, 'conflicto', 'Conflicto'],
    [422, 'datos-invalidos', 'Datos inválidos'],
    [429, 'demasiadas-solicitudes', 'Demasiadas solicitudes'],
    [503, 'servicio-no-disponible', 'Servicio no disponible'],
]);

it('answers a wrong verb as method not allowed', function () {
    deleteJson('/api/v1')
        ->assertMethodNotAllowed()
        ->assertJsonPath('type', 'http://localhost/problemas/metodo-no-permitido');
});

it('keeps the headers of an http exception', function () {
    getJson('/api/v1/pruebas/limite')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '30')
        ->assertJsonPath('type', 'http://localhost/problemas/demasiadas-solicitudes');
});

it('hides every technical detail of an unexpected failure', function () {
    $response = getJson('/api/v1/pruebas/fallo');

    $response->assertInternalServerError()
        ->assertHeader('Content-Type', PROBLEM_CONTENT_TYPE)
        ->assertJsonPath('type', 'http://localhost/problemas/error-interno')
        ->assertJsonPath('detail', 'Ocurrió un error inesperado. Intente de nuevo más tarde.');

    expect(array_keys($response->json()))->toBe(['type', 'title', 'status', 'detail', 'instance']);

    foreach (['SQLSTATE', 'secret.php', 'RuntimeException'] as $leak) {
        expect((string) $response->getContent())->not->toContain($leak);
    }
});

it('answers a path identifier of another business exactly like one that does not exist', function () {
    getJson('/api/v1/pruebas/recursos/'.OWN_RESOURCE_ID)->assertNoContent();

    $foreign = getJson('/api/v1/pruebas/recursos/'.FOREIGN_RESOURCE_ID)->assertNotFound();
    $missing = getJson('/api/v1/pruebas/recursos/'.MISSING_RESOURCE_ID)->assertNotFound();

    expect(Arr::except($foreign->json(), 'instance'))
        ->toBe(Arr::except($missing->json(), 'instance'))
        ->toMatchArray(['type' => 'http://localhost/problemas/no-encontrado']);
});

it('answers a body identifier of another business as invalid data in that field', function () {
    postJson('/api/v1/pruebas/recursos', ['recurso_id' => OWN_RESOURCE_ID])->assertNoContent();

    $foreign = postJson('/api/v1/pruebas/recursos', ['recurso_id' => FOREIGN_RESOURCE_ID])->assertUnprocessable();
    $missing = postJson('/api/v1/pruebas/recursos', ['recurso_id' => MISSING_RESOURCE_ID])->assertUnprocessable();

    expect(Arr::except($foreign->json(), 'instance'))
        ->toBe(Arr::except($missing->json(), 'instance'))
        ->toMatchArray(['type' => 'http://localhost/problemas/datos-invalidos'])
        ->and($foreign->json('errores'))->toHaveKey('recurso_id');
});

it('gives every problem type a spanish title and detail', function () {
    foreach (ProblemType::cases() as $type) {
        expect(trans()->has("problems.{$type->value}.title", 'es', false))->toBeTrue($type->value)
            ->and(trans()->has("problems.{$type->value}.detail", 'es', false))->toBeTrue($type->value);
    }
});
