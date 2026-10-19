<?php

declare(strict_types=1);

use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Accounts\Users\Infrastructure\Persistence\Repositories\EloquentUserRepository;
use Src\Shared\Domain\ValueObjects\UserId;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\assertDatabaseCount;

pest()->use(RefreshDatabaseAsOwner::class);

/** Build a new account with a fresh identity and a bcrypt hash. */
function newAccount(EloquentUserRepository $repository, string $email, string $name = 'Valeria Solano'): User
{
    return User::create(
        $repository->nextId(),
        new UserName($name),
        new Email($email),
        password_hash('clave-segura', PASSWORD_BCRYPT, ['cost' => 4]),
    );
}

it('reads a saved account back unchanged by its email', function () {
    $repository = new EloquentUserRepository;
    $user = newAccount($repository, 'duena@archivumparfums.test');

    $repository->save($user);

    expect($repository->findByEmail(new Email('duena@archivumparfums.test')))->toEqual($user);
});

it('finds the account whatever the spelling the person types', function () {
    $repository = new EloquentUserRepository;
    $repository->save(newAccount($repository, 'duena@archivumparfums.test'));

    expect($repository->findByEmail(new Email('  Duena@ArchivumParfums.TEST ')))->not->toBeNull();
});

it('finds nothing for an unknown email', function () {
    UserModel::factory()->create();

    expect((new EloquentUserRepository)->findByEmail(new Email('nadie@archivumparfums.test')))->toBeNull();
});

it('reads a saved account back unchanged by its identity', function () {
    $repository = new EloquentUserRepository;
    $user = newAccount($repository, 'duena@archivumparfums.test');

    $repository->save($user);

    expect($repository->find($user->id))->toEqual($user);
});

it('finds nothing for an unknown identity', function () {
    UserModel::factory()->create();

    expect((new EloquentUserRepository)->find(new UserId('0192f0c4-0000-7000-8000-0000000000ff')))->toBeNull();
});

it('stores the hash the aggregate carries and hides it from serialization', function () {
    $repository = new EloquentUserRepository;
    $user = newAccount($repository, 'duena@archivumparfums.test');

    $repository->save($user);

    $model = UserModel::query()->sole();
    expect($model->password)->toBe($user->passwordHash)
        ->and($model->toArray())->not->toHaveKey('password');
});

it('updates the row of an account saved again instead of adding another', function () {
    $repository = new EloquentUserRepository;
    $user = newAccount($repository, 'duena@archivumparfums.test');
    $repository->save($user);

    $repository->save(User::reconstitute($user->id, new UserName('Valeria Solano Ruiz'), $user->email, $user->passwordHash));

    assertDatabaseCount('users', 1);
    expect(UserModel::query()->sole()->name)->toBe('Valeria Solano Ruiz');
});

it('raises the domain error for a repeated email and keeps the transaction usable', function () {
    $repository = new EloquentUserRepository;
    $repository->save(newAccount($repository, 'duena@archivumparfums.test'));

    expect(fn () => $repository->save(newAccount($repository, 'duena@archivumparfums.test', 'Otra persona')))
        ->toThrow(EmailAlreadyRegisteredException::class);

    assertDatabaseCount('users', 1);
});
