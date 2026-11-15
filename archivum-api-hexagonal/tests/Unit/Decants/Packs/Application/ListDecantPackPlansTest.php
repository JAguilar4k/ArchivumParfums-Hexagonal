<?php

declare(strict_types=1);

use Src\Decants\Packs\Application\Contracts\PublicPackPlanReader;
use Src\Decants\Packs\Application\DTOs\PackPlanOnSale;
use Src\Decants\Packs\Application\UseCases\ListDecantPackPlans;
use Src\Decants\Packs\Application\UseCases\ListPackPlansOnSale;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\InMemoryDecantPackPlanRepository;

/** An offer of a business with the given name, on sale or not. */
function listedPackPlan(InMemoryDecantPackPlanRepository $plans, string $business, string $name, bool $active = true): DecantPackPlan
{
    $plan = DecantPackPlan::create($plans->nextId(), new BusinessId($business), new PlanName($name), new DecantCount(5),
        new MlPerDecant(5), new PackPrice(40000), new ValidityDays(180), null);

    if (! $active) {
        $plan->deactivate();
    }

    $plans->save($plan);

    return $plan;
}

it('lists every offer of the business of the session, on sale or not, by name', function () {
    $context = new FixedBusinessContext(new BusinessId('0192f0c4-eeee-7000-9000-000000000001'));
    $plans = new InMemoryDecantPackPlanRepository($context);
    $second = listedPackPlan($plans, '0192f0c4-eeee-7000-9000-000000000001', 'Verano', active: false);
    $first = listedPackPlan($plans, '0192f0c4-eeee-7000-9000-000000000001', 'Descubrimiento');
    listedPackPlan($plans, '0192f0c4-eeee-7000-9000-000000000002', 'De otra tienda');

    expect(new ListDecantPackPlans($plans)->execute())->toBe([$first, $second]);
});

it('lists the offers on sale exactly as the reader of the store of the address answers them', function () {
    $onSale = [new PackPlanOnSale('0192f0c4-0000-7000-9000-000000000001', 'Descubrimiento', 5, 5, 40000, 180, 2500)];
    $reader = new readonly class($onSale) implements PublicPackPlanReader
    {
        /** @param  list<PackPlanOnSale>  $onSale */
        public function __construct(private array $onSale) {}

        public function onSale(): array
        {
            return $this->onSale;
        }
    };

    expect(new ListPackPlansOnSale($reader)->execute())->toBe($onSale);
});
