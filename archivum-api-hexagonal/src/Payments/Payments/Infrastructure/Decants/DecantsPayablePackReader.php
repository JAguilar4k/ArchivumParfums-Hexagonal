<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Decants;

use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Decants\Packs\Application\DTOs\ActivateDecantPackCommand;
use Src\Decants\Packs\Application\UseCases\ActivateDecantPack;
use Src\Decants\Packs\Domain\Contracts\DecantPackPlanRepository;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Payments\Payments\Application\Contracts\PayablePackReader;
use Src\Payments\Payments\Application\DTOs\PayablePack;
use Src\Payments\Payments\Domain\Contracts\ExchangeRates;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Shared\Domain\Contracts\BusinessContext;

/** The decant packs of the Decants module as payments need them, like SalesPayableReader does with the orders. */
final readonly class DecantsPayablePackReader implements PayablePackReader
{
    public function __construct(
        private DecantPackRepository $packs,
        private DecantPackPlanRepository $plans,
        private BusinessRepository $businesses,
        private BusinessContext $business,
        private ExchangeRates $exchangeRates,
        private ActivateDecantPack $activate,
    ) {}

    public function findForPayment(string $packId, string $userId): PayablePack
    {
        $pack = $this->lock($packId);
        if (! $this->isCustomerOf($pack, $userId)) {
            throw new PaymentNotFoundException('payments.not_found');
        }
        if ($pack->status === DecantPackStatus::PendingPayment && $pack->isOverdueAt(now()->toImmutable())) {
            throw new InvalidPaymentTransitionException('payments.not_payable');
        }

        return $this->payable($pack, startingPayment: true);
    }

    public function findForUpdate(string $packId): PayablePack
    {
        return $this->payable($this->lock($packId), startingPayment: false);
    }

    public function applyPayment(string $packId): void
    {
        $this->activate->execute(new ActivateDecantPackCommand($packId, now()->toIso8601String()));
    }

    /** The pack of the current business, locked until the transaction ends. */
    private function lock(string $packId): DecantPack
    {
        return $this->packs->find(new DecantPackId($packId), forUpdate: true) ?? throw new PaymentNotFoundException('payments.not_found');
    }

    /** Whether the user is the customer who bought the pack in the current business. */
    private function isCustomerOf(DecantPack $pack, string $userId): bool
    {
        return CustomerModel::query()->where('business_id', $this->business->current()->value)->whereKey($pack->customerId)->where('user_id', $userId)->exists();
    }

    /** The price is the one of the stored offer, never one the browser sends; only a new PayPal charge needs the exchange rate. */
    private function payable(DecantPack $pack, bool $startingPayment): PayablePack
    {
        $businessId = $this->business->current();
        $business = $this->businesses->find($businessId) ?? throw new PaymentNotFoundException('payments.not_found');
        $plan = $this->plans->find($pack->planId) ?? throw new PaymentNotFoundException('payments.not_found');
        $rate = $startingPayment && $business->settings->defaultGateway === 'paypal' ? $this->exchangeRates->current() : null;

        return new PayablePack($pack->id->value, $businessId->value, $pack->status->value, $plan->price->amount, $business->settings->defaultGateway, $rate?->value);
    }
}
