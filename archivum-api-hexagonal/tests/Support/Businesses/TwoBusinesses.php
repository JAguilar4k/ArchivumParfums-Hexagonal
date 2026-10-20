<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use Laravel\Sanctum\Sanctum;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;

/**
 * Two businesses made with the factories, each with its staff and customers, to prove that each one only reaches
 * its own rows. They do not use the demo seeders, which serve the live demo.
 */
final class TwoBusinesses
{
    public const string FIRST = 'perfumeria-primera';

    public const string SECOND = 'perfumeria-segunda';

    /** Name of each business by its slug. */
    public const array NAMES = [
        self::FIRST => 'Perfumería Primera',
        self::SECOND => 'Perfumería Segunda',
    ];

    /** Owner of each business by its slug. */
    public const array OWNERS = [
        self::FIRST => 'duena@perfumeriaprimera.test',
        self::SECOND => 'dueno@perfumeriasegunda.test',
    ];

    /** Staff of the first business, without the permissions of an owner. */
    public const string ADVISOR = 'asesor@perfumeriaprimera.test';

    /** Customer of the first business only. */
    public const string CUSTOMER = 'cliente@clientes.test';

    /** Customer of both businesses, with one customer record in each. */
    public const string CUSTOMER_OF_BOTH = 'cliente-de-las-dos@clientes.test';

    /** Create both businesses, the owner of each one, the advisor of the first one and the customers. */
    public static function create(): void
    {
        $customerOfBoth = self::account(self::CUSTOMER_OF_BOTH);

        foreach (self::NAMES as $slug => $name) {
            $businessId = BusinessModel::factory()->create(['name' => $name, 'slug' => $slug])->id;

            MemberModel::factory()->owner()->create(['business_id' => $businessId, 'user_id' => self::account(self::OWNERS[$slug])]);
            CustomerModel::factory()->create(['business_id' => $businessId, 'user_id' => $customerOfBoth]);
        }

        MemberModel::factory()->staff()->create(['business_id' => self::idOf(self::FIRST), 'user_id' => self::account(self::ADVISOR)]);
        CustomerModel::factory()->create(['business_id' => self::idOf(self::FIRST), 'user_id' => self::account(self::CUSTOMER)]);
    }

    /** Identifier of one of the two businesses. */
    public static function idOf(string $slug): string
    {
        return BusinessModel::query()->where('slug', $slug)->sole()->id;
    }

    /** Authenticate the account of an email for the next requests, with every ability, as a sign-in gives it. */
    public static function actAs(string $email): UserModel
    {
        $user = UserModel::query()->where('email', $email)->sole();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /** Authenticate the owner of one of the two businesses. */
    public static function actAsOwnerOf(string $slug): UserModel
    {
        return self::actAs(self::OWNERS[$slug]);
    }

    /** Create the account of an email and return its identifier. */
    private static function account(string $email): string
    {
        return UserModel::factory()->create(['email' => $email])->id;
    }
}
