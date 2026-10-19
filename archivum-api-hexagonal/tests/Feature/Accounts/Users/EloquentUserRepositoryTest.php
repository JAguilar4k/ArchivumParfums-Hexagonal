<?php

declare(strict_types=1);

use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('reads existing account names without truncating them by email', function (string $name) {
    $model = UserModel::factory()->create(['name' => $name, 'email' => 'legacy@archivum.test']);
    $repository = app(UserRepository::class);

    $byEmail = $repository->findByEmail(new Email('legacy@archivum.test'));

    expect($byEmail?->id->value)->toBe($model->id)
        ->and($byEmail?->name->value)->toBe($name);
})->with([
    'existing account with 150 characters' => str_repeat('a', 150),
    'database limit of 255 characters' => str_repeat('a', 255),
]);
