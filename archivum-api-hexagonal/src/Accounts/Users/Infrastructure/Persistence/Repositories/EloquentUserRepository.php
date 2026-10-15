<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Persistence\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class EloquentUserRepository implements UserRepository
{
    /** Generate a time-ordered UUID for a new account. */
    public function nextId(): UserId
    {
        return new UserId((string) Str::uuid7());
    }

    /** Insert or update the account inside a savepoint and translate a repeated email. */
    public function save(User $user): void
    {
        try {
            DB::transaction(fn () => UserModel::query()->updateOrCreate(
                ['id' => $user->id->value],
                [
                    'name' => $user->name->value,
                    'email' => $user->email->value,
                    'password' => $user->passwordHash,
                ],
            ));
        } catch (UniqueConstraintViolationException) {
            throw EmailAlreadyRegisteredException::create();
        }
    }

    /** Find the account by its normalised email. */
    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::query()->whereRaw('lower(email) = ?', [$email->value])->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** Find an account by its identity. */
    public function find(UserId $id): ?User
    {
        $model = UserModel::query()->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(UserModel $model): User
    {
        return User::reconstitute(
            new UserId($model->id),
            new UserName($model->name),
            new Email($model->email),
            $model->password,
        );
    }
}
