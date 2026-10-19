<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use Illuminate\Support\Facades\Auth;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Accounts\DemoAccounts;

use function Pest\Laravel\withToken;

/**
 * The two demo businesses of the seeders, to prove that each one only reaches its own rows.
 */
final class TwoBusinesses
{
    public const string FIRST = 'archivumparfums';

    public const string SECOND = 'esencias-del-norte';

    /** Owner of each business by its slug. */
    public const array OWNERS = [
        self::FIRST => 'duena@archivumparfums.test',
        self::SECOND => 'dueno@esenciasdelnorte.test',
    ];

    public const string ADVISOR = 'asesor@archivumparfums.test';

    public const string CUSTOMER = 'mariana@clientes.test';

    /** Seed both businesses with their staff and customers. */
    public static function seed(): void
    {
        DemoAccounts::seed();
    }

    /** Identifier of one of the two businesses. */
    public static function idOf(string $slug): string
    {
        return BusinessModel::query()->where('slug', $slug)->sole()->id;
    }

    /** Send the next requests with a real token of the account of an email, as after signing in. */
    public static function actAs(string $email): UserModel
    {
        $user = UserModel::query()->where('email', $email)->sole();

        Auth::forgetGuards();
        withToken($user->createToken('Computadora')->plainTextToken);

        return $user;
    }

    /** Authenticate the owner of one of the two businesses. */
    public static function actAsOwnerOf(string $slug): UserModel
    {
        return self::actAs(self::OWNERS[$slug]);
    }

    /** Make the next queries of the test reach only the rows of one business, to prepare the rows of each one. */
    public static function scopeQueriesTo(?string $businessId): void
    {
        app(BusinessTransaction::class)->scope($businessId);
    }
}
