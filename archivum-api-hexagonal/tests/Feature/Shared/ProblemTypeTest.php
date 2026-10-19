<?php

declare(strict_types=1);

use Src\Shared\Infrastructure\Http\Problem\ProblemType;

it('resolves every status to a type that answers with it, and a 401 of the framework to no session', function () {
    foreach (ProblemType::cases() as $type) {
        expect(ProblemType::fromStatus($type->status())->status())->toBe($type->status());
    }

    expect(ProblemType::fromStatus(401))->toBe(ProblemType::Unauthenticated);
});

it('falls back by error class for a status outside the catalog', function (int $status, ProblemType $expected) {
    expect(ProblemType::fromStatus($status))->toBe($expected);
})->with([
    'client error' => [418, ProblemType::BadRequest],
    'server error' => [502, ProblemType::InternalError],
]);
