<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use Database\Seeders\DemoBusinessesSeeder;
use Illuminate\Support\Facades\Auth;
use Src\Accounts\Sessions\Infrastructure\Security\SanctumTokenIssuer;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

use function Pest\Laravel\withToken;

/**
 * The two demo businesses of the seeders, to prove that each one only reaches its own rows.
 */
final class TwoBusinesses
{
    public const string FIRST = 'archivumparfums';

    public const string SECOND = 'esencias-del-norte';

    public const string PASSWORD = 'clave-de-demostracion';

    /** Owner of each business by its slug. */
    public const array OWNERS = [
        self::FIRST => 'duena@archivumparfums.test',
        self::SECOND => 'dueno@esenciasdelnorte.test',
    ];

    public const string ADVISOR = 'asesor@archivumparfums.test';

    public const string CUSTOMER = 'mariana@clientes.test';

    public const string CUSTOMER_OF_BOTH = 'sofia@clientes.test';

    /** Seed both businesses with their staff and customers. */
    public static function seed(): void
    {
        config(['archivum.demo_password' => self::PASSWORD]);

        app(DemoBusinessesSeeder::class)->run();
    }

    /** Identifier of one of the two businesses. */
    public static function idOf(string $slug): string
    {
        return BusinessModel::query()->where('slug', $slug)->sole()->id;
    }

    /** Identifier of the account of an email. */
    public static function userIdOf(string $email): string
    {
        return UserModel::query()->where('email', $email)->sole()->id;
    }

    /** Send the next requests with a real token of the account of an email, as after signing in. */
    public static function actAs(string $email): UserModel
    {
        $user = UserModel::query()->where('email', $email)->sole();

        Auth::forgetGuards();
        withToken($user->createToken(SanctumTokenIssuer::TOKEN_NAME)->plainTextToken);

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
