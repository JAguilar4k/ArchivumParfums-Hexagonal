<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;
use Src\Accounts\Users\Application\DTOs\RegisterAccountCommand;
use Src\Accounts\Users\Application\UseCases\RegisterAccount;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Members\Application\DTOs\AddMemberCommand;
use Src\Businesses\Members\Application\UseCases\AddMember;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Businesses\Profile\Application\DTOs\RegisterBusinessCommand;
use Src\Businesses\Profile\Application\UseCases\RegisterBusiness;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final class DemoBusinessesSeeder extends Seeder
{
    /**
     * Two fictitious businesses: ArchivumParfums, the pilot, and a second one to show the isolation.
     * Names are invented, emails use the reserved .test domain and phones are test numbers.
     *
     * @var list<array{
     *     name: string,
     *     slug: string,
     *     place: string,
     *     owner: array{string, string},
     *     staff: list<array{string, string, MemberRole, bool}>,
     *     customers: list<array{string, string, string}>,
     * }>
     */
    public const array BUSINESSES = [
        [
            'name' => 'ArchivumParfums',
            'slug' => 'archivumparfums',
            'place' => 'Escazú, San Rafael, 200 m norte del parque central',
            'owner' => ['Valeria Solano Ruiz', 'duena@archivumparfums.test'],
            'staff' => [
                ['Daniela Mora Vargas', 'coduena@archivumparfums.test', MemberRole::Owner, false],
                ['Andrés Quesada Rojas', 'asesor@archivumparfums.test', MemberRole::Staff, true],
            ],
            'customers' => [
                ['Mariana Jiménez Soto', 'mariana@clientes.test', '+50688880001'],
                ['Carlos Herrera Mena', 'carlos@clientes.test', '+50688880002'],
                ['Sofía Araya Brenes', 'sofia@clientes.test', '+50688880003'],
            ],
        ],
        [
            'name' => 'Esencias del Norte',
            'slug' => 'esencias-del-norte',
            'place' => 'Ciudad Quesada, 100 m sur del mercado',
            'owner' => ['Rodrigo Campos Alfaro', 'dueno@esenciasdelnorte.test'],
            'staff' => [],
            'customers' => [
                ['Lucía Brenes Castro', 'lucia@clientes.test', '+50688880004'],
                ['Sofía Araya Brenes', 'sofia@clientes.test', '+50688880003'],
            ],
        ],
    ];

    /** Receive the use cases that register businesses, accounts and staff. */
    public function __construct(
        private readonly RegisterBusiness $registerBusiness,
        private readonly RegisterAccount $registerAccount,
        private readonly AddMember $addMember,
        private readonly UserRepository $users,
        private readonly BusinessTransaction $transaction,
    ) {}

    /** Seed every demo business that does not exist yet, each in its own transaction, so running it twice changes nothing. */
    public function run(): void
    {
        $password = config('archivum.demo_password');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Set ARCHIVUM_DEMO_PASSWORD in .env before seeding the demo businesses.');
        }

        foreach (self::BUSINESSES as $business) {
            if (BusinessModel::query()->where('slug', $business['slug'])->exists()) {
                continue;
            }

            $this->transaction->run(null, fn () => $this->seedBusiness($business, $password));
        }
    }

    /**
     * Register one business with its owner, then pin it in the transaction and add the rest of its staff and its customers.
     *
     * @param  array{name: string, slug: string, place: string, owner: array{string, string}, staff: list<array{string, string, MemberRole, bool}>, customers: list<array{string, string, string}>}  $business
     */
    private function seedBusiness(array $business, string $password): void
    {
        [$ownerName, $ownerEmail] = $business['owner'];

        $registered = $this->registerBusiness->execute(new RegisterBusinessCommand(
            $business['name'],
            $business['slug'],
            $ownerName,
            $ownerEmail,
            $password,
            BusinessTimezone::DEFAULT,
            $business['place'],
            true,
        ));

        $this->transaction->scope($registered->id->value);

        foreach ($business['staff'] as [$name, $email, $role, $attendsAppointments]) {
            $this->addMember->execute(new AddMemberCommand(
                $this->accountId($name, $email, $password),
                $registered->id->value,
                $role,
                $attendsAppointments,
            ));
        }

        foreach ($business['customers'] as [$name, $email, $phone]) {
            CustomerModel::query()->create([
                'business_id' => $registered->id->value,
                'user_id' => $this->accountId($name, $email, $password),
                'phone' => $phone,
            ]);
        }
    }

    /** Reuse the account of an email, so one customer can be in both businesses, or open it. */
    private function accountId(string $name, string $email, string $password): string
    {
        $existing = $this->users->findByEmail(new Email($email));

        return $existing === null
            ? $this->registerAccount->execute(new RegisterAccountCommand($name, $email, $password))->id->value
            : $existing->id->value;
    }
}
