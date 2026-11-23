<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use Database\Seeders\PlatformAdminSeeder;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

/**
 * Seed the demo data and sign in with its accounts through POST /api/v1/sesiones, as a client of the API does.
 */
final class DemoAccounts
{
    public const string PASSWORD = 'clave-de-demostracion';

    /** Email of the administrator of the platform that seedPlatformAdmin() opens (#347). */
    public const string PLATFORM_ADMIN = 'dev@archivumparfums.test';

    /** Seed the demo businesses, their staff and their customers, every account with the password of the tests. */
    public static function seed(): void
    {
        config(['archivum.demo_password' => self::PASSWORD]);
        seed();
    }

    /** Open the administrator of the platform with the password of the tests, as .env would configure it. */
    public static function seedPlatformAdmin(): void
    {
        config(['archivum.platform_admin' => ['email' => self::PLATFORM_ADMIN, 'password' => self::PASSWORD]]);
        seed(PlatformAdminSeeder::class);
    }

    /**
     * Sign in as a demo account from a device and return the bearer header of the new session.
     *
     * @return array<string, string>
     */
    public static function signIn(string $email, string $device = 'Computadora'): array
    {
        $token = postJson('/api/v1/sesiones', ['correo' => $email, 'contrasena' => self::PASSWORD, 'dispositivo' => $device])
            ->assertCreated()
            ->json('data.token');

        return ['Authorization' => "Bearer {$token}"];
    }
}
