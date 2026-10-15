<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use SensitiveParameter;
use Src\Businesses\Profile\Application\Contracts\OwnerAccounts;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Throwable;

final class InMemoryOwnerAccounts implements OwnerAccounts
{
    /** @var list<array{businessId: string, name: string, email: string, password: string, attendsAppointments: bool}> */
    private array $registered = [];

    private ?Throwable $failure = null;

    /** Make every later registration fail with the given error. */
    public function failWith(Throwable $failure): void
    {
        $this->failure = $failure;
    }

    /** Record the owner unless a failure was set. */
    public function registerOwner(
        BusinessId $businessId,
        string $name,
        string $email,
        #[SensitiveParameter] string $password,
        bool $attendsAppointments,
    ): void {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->registered[] = [
            'businessId' => $businessId->value,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'attendsAppointments' => $attendsAppointments,
        ];
    }

    /**
     * List the owners registered so far.
     *
     * @return list<array{businessId: string, name: string, email: string, password: string, attendsAppointments: bool}>
     */
    public function registered(): array
    {
        return $this->registered;
    }
}
