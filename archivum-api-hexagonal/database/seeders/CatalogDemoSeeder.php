<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\SeededBusinessContext;
use Illuminate\Database\Seeder;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Catalog\Perfumes\Application\DTOs\AddPerfumeVariantCommand;
use Src\Catalog\Perfumes\Application\DTOs\CreatePerfumeCommand;
use Src\Catalog\Perfumes\Application\UseCases\AddPerfumeVariant;
use Src\Catalog\Perfumes\Application\UseCases\CreatePerfume;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Repositories\EloquentPerfumeRepository;
use Src\Catalog\Perfumes\Infrastructure\Pricing\BusinessSettingsBottleMargin;
use Src\Catalog\Perfumes\Infrastructure\Pricing\NoSupplierCost;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Persistence\DatabaseTransactionRunner;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final class CatalogDemoSeeder extends Seeder
{
    /**
     * Fictitious perfumes of each demo business: brand, name, concentration, gender, family, notes by layer, tags by kind
     * and the presentations as milliliters and manual price in colones. There is no supplier cost yet, so every price is manual.
     *
     * @var array<string, list<array{
     *     string, string, string, string, string,
     *     array{top: list<string>, heart: list<string>, base: list<string>},
     *     array{time_of_day: list<string>, climate: list<string>, occasion: list<string>},
     *     list<array{int, int}>,
     * }>>
     */
    private const array CATALOG = [
        BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID => [
            [
                'Maison Noctis', 'Oud Nocturno', 'EDP', 'unisex', 'Amaderada',
                ['top' => ['Cardamomo', 'Bergamota'], 'heart' => ['Rosa', 'Incienso'], 'base' => ['Oud', 'Ámbar']],
                ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => ['Cena']],
                [[50, 185000], [100, 295000]],
            ],
            [
                'Casa Aurora', 'Alba de Azahar', 'EDT', 'femenino', 'Floral',
                ['top' => ['Neroli', 'Mandarina'], 'heart' => ['Azahar'], 'base' => ['Almizcle']],
                ['time_of_day' => ['day'], 'climate' => ['warm'], 'occasion' => ['Boda']],
                [[50, 98000], [100, 150000]],
            ],
            [
                'Atelier Brisa', 'Sal Marina', 'EDT', 'unisex', 'Acuática',
                ['top' => ['Limón'], 'heart' => ['Sal'], 'base' => ['Madera flotada']],
                ['time_of_day' => ['day', 'afternoon'], 'climate' => ['warm'], 'occasion' => ['Playa']],
                [[100, 120000]],
            ],
            [
                'Maison Noctis', 'Tabaco Dulce', 'EDP', 'masculino', 'Oriental',
                ['top' => ['Pimienta rosa'], 'heart' => ['Tabaco'], 'base' => ['Vainilla', 'Haba tonka']],
                ['time_of_day' => ['night'], 'climate' => ['temperate'], 'occasion' => ['Cita']],
                [[50, 210000]],
            ],
        ],
        BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID => [
            [
                'Bosque Niebla', 'Cedro y Bruma', 'EDP', 'masculino', 'Amaderada',
                ['top' => ['Pino'], 'heart' => ['Cedro'], 'base' => ['Vetiver', 'Musgo']],
                ['time_of_day' => ['day', 'afternoon'], 'climate' => ['cold'], 'occasion' => ['Oficina']],
                [[50, 135000], [100, 205000]],
            ],
            [
                'Casa Volcán', 'Café y Vainilla', 'EDP', 'unisex', 'Gourmand',
                ['top' => ['Café'], 'heart' => ['Caramelo'], 'base' => ['Vainilla']],
                ['time_of_day' => ['afternoon'], 'climate' => ['temperate'], 'occasion' => ['Tarde de lluvia']],
                [[50, 115000]],
            ],
            [
                'Flora Quesada', 'Jardín de Orquídeas', 'EDT', 'femenino', 'Floral',
                ['top' => ['Pera'], 'heart' => ['Orquídea', 'Jazmín'], 'base' => ['Sándalo']],
                ['time_of_day' => ['day'], 'climate' => ['temperate'], 'occasion' => ['Paseo']],
                [[100, 160000]],
            ],
        ],
    ];

    /** Seed the sample perfumes of each demo business, in the transaction of that business and without repeating the ones already there. */
    public function run(): void
    {
        foreach (self::CATALOG as $businessId => $perfumes) {
            new BusinessTransaction()->run($businessId, function () use ($businessId, $perfumes): void {
                $context = new SeededBusinessContext(new BusinessId($businessId));
                $repository = new EloquentPerfumeRepository($context);
                $transaction = new DatabaseTransactionRunner;
                $createPerfume = new CreatePerfume($repository, $context, $transaction);
                $addVariant = new AddPerfumeVariant(
                    $repository,
                    new NoSupplierCost,
                    new BusinessSettingsBottleMargin($context, app(BusinessRepository::class)),
                    $transaction,
                );

                foreach ($perfumes as [$brand, $name, $concentration, $gender, $family, $notes, $tags, $variants]) {
                    if (PerfumeModel::query()->where('brand', $brand)->where('name', $name)->where('concentration', $concentration)->exists()) {
                        continue;
                    }

                    $perfume = $createPerfume->execute(new CreatePerfumeCommand(
                        $brand, $name, $concentration, $gender, $family, null, $notes, $tags, true,
                    ));

                    foreach ($variants as [$ml, $manualPrice]) {
                        $addVariant->execute(new AddPerfumeVariantCommand($perfume->id->value, $ml, $manualPrice));
                    }
                }
            });
        }
    }
}
