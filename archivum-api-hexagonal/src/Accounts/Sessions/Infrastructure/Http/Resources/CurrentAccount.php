<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Accounts\Sessions\Application\DTOs\CurrentAccountView;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;

/**
 * @property-read CurrentAccountView $resource
 */
final class CurrentAccount extends JsonResource
{
    /** Wrap the data of the signed-in account. */
    public function __construct(private readonly CurrentAccountView $account)
    {
        parent::__construct($account);
    }

    /**
     * Expose only the listed fields: never the password, its hash or the tokens.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $membership = $this->account->membership;

        return [
            'id' => $this->account->id,
            'nombre' => $this->account->name,
            'correo' => $this->account->email,
            'rol' => match ($this->account->role) {
                AccountRole::Owner => 'dueno',
                AccountRole::Staff => 'colaborador',
                AccountRole::Customer => 'cliente',
            },
            'tienda' => $membership === null ? null : [
                'id' => $membership->businessId,
                'nombre' => $membership->businessName,
                'slug' => $membership->businessSlug,
            ],
        ];
    }
}
