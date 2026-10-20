<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;

/**
 * @extends Factory<UserModel>
 */
final class UserFactory extends Factory
{
    public const string PASSWORD = 'password';

    protected $model = UserModel::class;

    private static ?string $passwordHash = null;

    /**
     * Default state of a fictitious account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->userName().'@archivum.test',
            'password' => self::$passwordHash ??= Hash::make(self::PASSWORD),
        ];
    }
}
