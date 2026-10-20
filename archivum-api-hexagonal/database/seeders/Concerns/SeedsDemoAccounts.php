<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;

trait SeedsDemoAccounts
{
    /** Hash the password of ARCHIVUM_DEMO_PASSWORD, refusing to seed demo accounts without one. */
    private function demoPasswordHash(): string
    {
        $password = config('archivum.demo_password');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException(__('businesses.demo_password_missing'));
        }

        return Hash::make($password);
    }

    /** Open the demo account of an email, or update its name and password, and return its identifier. */
    private function demoAccount(string $email, string $name, string $passwordHash): string
    {
        return UserModel::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $passwordHash],
        )->id;
    }
}
