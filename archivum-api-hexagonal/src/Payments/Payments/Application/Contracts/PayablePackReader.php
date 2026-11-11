<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

use Src\Payments\Payments\Application\DTOs\PayablePack;

/** The decant packs a payment can pay (#145), without Payments knowing the domain of Decants. */
interface PayablePackReader
{
    /** Lock the pack this user bought in the current business, to start its payment; past its payment deadline it is not payable. */
    public function findForPayment(string $packId, string $userId): PayablePack;

    /** Lock the pack a payment pays, with no customer: a verified notice of the provider confirms it, not a person. */
    public function findForUpdate(string $packId): PayablePack;

    /** The payment of a pack that waited for it was confirmed: the pack becomes active from this moment (R33). */
    public function applyPayment(string $packId): void;
}
