<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use Src\Decants\Packs\Application\UseCases\PurchaseDecantPack;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Shared\RecordingTransactionRunner;

/** The purchase of a pack wired to in-memory ports, in a store that sells the offer Descubrimiento: 5 decants of 5 ml. */
final readonly class PackPurchaseWiring
{
    public const string BUSINESS = '0192f0c4-eeee-7000-9000-000000000001';

    private function __construct(
        public PurchaseDecantPack $purchase,
        public DecantPackPlan $plan,
        public FixedBusinessContext $context,
        public InMemoryDecantPackPlanRepository $plans,
        public InMemoryDecantPackRepository $packs,
        public FakePackCustomers $customers,
        public FakePackPayments $payments,
        public RecordingTransactionRunner $transaction,
    ) {}

    /** Wire the use case; the store gives $minutes to pay a pack. */
    public static function create(int $minutes = 30): self
    {
        $context = new FixedBusinessContext(new BusinessId(self::BUSINESS));
        $plans = new InMemoryDecantPackPlanRepository($context);
        $plan = DecantPackPlan::create($plans->nextId(), $context->current(), new PlanName('Descubrimiento'), new DecantCount(5),
            new MlPerDecant(5), new PackPrice(40000), new ValidityDays(180), new MaxPricePerMl(2500));
        $plans->save($plan);
        $packs = new InMemoryDecantPackRepository($context);
        $customers = new FakePackCustomers;
        $payments = new FakePackPayments;
        $transaction = new RecordingTransactionRunner;

        return new self(new PurchaseDecantPack($packs, $plans, $customers, new FixedPaymentDeadline($minutes), $payments, $transaction),
            $plan, $context, $plans, $packs, $customers, $payments, $transaction);
    }

    /** The same purchase with another repository of packs, such as one that looks a key up too early. */
    public function withPacks(DecantPackRepository $packs): PurchaseDecantPack
    {
        return new PurchaseDecantPack($packs, $this->plans, $this->customers, new FixedPaymentDeadline, $this->payments, $this->transaction);
    }
}
