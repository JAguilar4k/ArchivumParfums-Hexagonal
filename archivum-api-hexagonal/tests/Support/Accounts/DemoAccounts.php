<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

/**
 * Seed the demo data and sign in with its accounts through POST /api/v1/sesiones, as a client of the API does.
 */
final class DemoAccounts
{
    public const string PASSWORD = 'clave-de-demostracion';

    /** Seed the demo businesses, their staff and their customers, every account with the password of the tests. */
    public static function seed(): void
    {
        config(['archivum.demo_password' => self::PASSWORD]);
        seed();
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
