<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;

final readonly class ApiIndexController
{
    /** Describe the API and the version being served. */
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'nombre' => config('app.name'),
            'version' => 'v1',
        ]);
    }
}
