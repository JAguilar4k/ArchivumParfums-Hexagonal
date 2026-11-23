<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;

final class PlatformAdminSeeder extends Seeder
{
    /** Name of the account; the email and the password come from .env. */
    public const string NAME = 'Administración de la plataforma';

    /**
     * Open the account that administers the platform (#347), or update its password, when .env sets both
     * ARCHIVUM_PLATFORM_ADMIN_EMAIL and ARCHIVUM_PLATFORM_ADMIN_PASSWORD. Without them it opens nothing, so no
     * password is ever written in the code.
     */
    public function run(): void
    {
        $email = config('archivum.platform_admin.email');
        $password = config('archivum.platform_admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return;
        }

        // The mark is not mass assignable, so only this seeder and the console command set it.
        UserModel::query()->firstOrNew(['email' => mb_strtolower(trim($email))])
            ->forceFill(['name' => self::NAME, 'password' => Hash::make($password), 'is_platform_admin' => true])
            ->save();
    }
}
