<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsDemoAccounts;
use Illuminate\Database\Seeder;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final class StaffSeeder extends Seeder
{
    use SeedsDemoAccounts;

    /**
     * Fictitious staff of each demo business: email, name, role and whether the person attends appointments.
     *
     * @var array<string, list<array{string, string, MemberRole, bool}>>
     */
    private const array STAFF = [
        BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID => [
            ['duena@archivumparfums.test', 'Valeria Solano Ruiz', MemberRole::Owner, true],
            ['coduena@archivumparfums.test', 'Daniela Mora Vargas', MemberRole::Owner, false],
            ['asesor@archivumparfums.test', 'Andrés Quesada Rojas', MemberRole::Staff, true],
        ],
        BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID => [
            ['dueno@esenciasdelnorte.test', 'Rodrigo Campos Alfaro', MemberRole::Owner, true],
        ],
    ];

    /** Seed the accounts and the staff of each demo business, in a transaction scoped to it, for local development only. */
    public function run(BusinessTransaction $transaction): void
    {
        $passwordHash = $this->demoPasswordHash();

        foreach (self::STAFF as $businessId => $staff) {
            $transaction->run($businessId, function () use ($businessId, $staff, $passwordHash): void {
                foreach ($staff as [$email, $name, $role, $attendsAppointments]) {
                    MemberModel::query()->updateOrCreate(
                        ['business_id' => $businessId, 'user_id' => $this->demoAccount($email, $name, $passwordHash)],
                        ['role' => $role->value, 'attends_appointments' => $attendsAppointments],
                    );
                }
            });
        }
    }
}
