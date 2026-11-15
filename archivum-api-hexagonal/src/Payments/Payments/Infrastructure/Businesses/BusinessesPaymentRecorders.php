<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Businesses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Src\Payments\Payments\Application\Contracts\PaymentRecorders;
use Src\Payments\Payments\Application\DTOs\PaymentRecorder;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class BusinessesPaymentRecorders implements PaymentRecorders
{
    /** Receive the business pinned for the request. */
    public function __construct(private BusinessContext $business) {}

    /**
     * Read the account among the owners of the pinned business, with its name. A member of the staff or an account
     * of another business is forbidden: the panel answers 403, as Sanctum does for a token without the ability.
     */
    public function ownerOf(string $userId): PaymentRecorder
    {
        $owner = DB::table('members')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('members.business_id', $this->business->current()->value)
            ->where('members.user_id', $userId)
            ->where('members.role', 'owner')
            ->first(['members.id', 'users.name']);

        if ($owner === null) {
            throw new AuthorizationException;
        }

        return new PaymentRecorder((string) $owner->id, (string) $owner->name);
    }
}
