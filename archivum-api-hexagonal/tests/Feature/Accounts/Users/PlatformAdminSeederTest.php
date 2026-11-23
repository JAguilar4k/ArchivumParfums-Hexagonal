<?php

declare(strict_types=1);

use Database\Seeders\PlatformAdminSeeder;
use Illuminate\Support\Facades\Hash;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class);

it('opens the administrator of the platform with the email and the password of the configuration', function () {
    config(['archivum.platform_admin' => ['email' => ' Dev@ArchivumParfums.test ', 'password' => 'clave-de-la-plataforma']]);

    seed(PlatformAdminSeeder::class);

    $admin = UserModel::query()->sole();

    expect($admin->email)->toBe('dev@archivumparfums.test')
        ->and($admin->name)->toBe('Administración de la plataforma')
        ->and(Hash::check('clave-de-la-plataforma', $admin->password))->toBeTrue()
        ->and($admin->getAttribute('is_platform_admin'))->toBeTrue();
});

it('updates the password when it is seeded again, without a second account', function () {
    config(['archivum.platform_admin' => ['email' => 'dev@archivumparfums.test', 'password' => 'clave-de-la-plataforma']]);
    seed(PlatformAdminSeeder::class);
    config(['archivum.platform_admin.password' => 'otra-clave-segura']);

    seed(PlatformAdminSeeder::class);

    expect(Hash::check('otra-clave-segura', UserModel::query()->sole()->password))->toBeTrue();
});

it('opens nothing without an email or a password', function (?string $email, ?string $password) {
    config(['archivum.platform_admin' => ['email' => $email, 'password' => $password]]);

    seed(PlatformAdminSeeder::class);

    assertDatabaseCount('users', 0);
})->with([
    'no password' => ['dev@archivumparfums.test', null],
    'empty password' => ['dev@archivumparfums.test', ''],
    'no email' => [null, 'clave-de-la-plataforma'],
]);
