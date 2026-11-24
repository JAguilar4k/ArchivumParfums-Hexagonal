<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\SeededAuditTrail;
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

/**
 * The real catalog of the pilot store (#355), for local installations and the class demo. instalar.bat runs it;
 * DatabaseSeeder does not, so the tests never load thousands of perfumes.
 */
final class PilotCatalogSeeder extends Seeder
{
    /** One perfume per line: brand, name, concentration, gender, description, notes, tags, presentations and photo key. */
    public const string CATALOG_FILE = __DIR__.'/data/archivumparfums-catalog.json';

    /** Same limit as perfumes.image_path. */
    private const int MAX_PHOTO_PATH_LENGTH = 255;

    private const int PERFUMES_PER_TRANSACTION = 100;

    public function run(): void
    {
        $this->seedFrom(self::CATALOG_FILE);
    }

    /**
     * Load the perfumes of a catalog file into the pilot store without repeating the ones already there. A photo is
     * stored as r2: plus R2_PUBLIC_URL and its key, so the file never carries the origin; without that origin the
     * perfume is loaded without photo, and a later run completes the photos that are missing.
     */
    public function seedFrom(string $path): void
    {
        /** @var list<array{brand: string, name: string, concentration?: string, gender?: string, family?: string, description?: string, is_active: bool, photo?: string, notes: array{top: list<string>, heart: list<string>, base: list<string>}, tags: array{time_of_day: list<string>, climate: list<string>, occasion: list<string>}, variants: list<array{ml: int, price: int}>}> $catalog */
        $catalog = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $origin = $this->photoOrigin();
        $created = 0;
        $photos = 0;

        foreach (array_chunk($catalog, self::PERFUMES_PER_TRANSACTION) as $chunk) {
            new BusinessTransaction()->run(BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, function () use ($chunk, $origin, &$created, &$photos): void {
                $context = new SeededBusinessContext(new BusinessId(BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID));
                $repository = new EloquentPerfumeRepository($context);
                $transaction = new DatabaseTransactionRunner;
                $createPerfume = new CreatePerfume($repository, $context, $transaction);
                $addVariant = new AddPerfumeVariant(
                    $repository,
                    new NoSupplierCost,
                    new BusinessSettingsBottleMargin($context, app(BusinessRepository::class)),
                    $transaction, new SeededAuditTrail,
                );

                foreach ($chunk as $perfume) {
                    $photoPath = $this->photoPath($origin, $perfume['photo'] ?? null);
                    $existing = PerfumeModel::query()
                        ->where('brand', $perfume['brand'])
                        ->where('name', $perfume['name'])
                        ->where('concentration', $perfume['concentration'] ?? null)
                        ->first();

                    if ($existing !== null) {
                        if ($photoPath !== null && $existing->image_path === null) {
                            $existing->forceFill(['image_path' => $photoPath])->save();
                            $photos++;
                        }

                        continue;
                    }

                    $stored = $createPerfume->execute(new CreatePerfumeCommand(
                        $perfume['brand'], $perfume['name'], $perfume['concentration'] ?? null, $perfume['gender'] ?? null,
                        $perfume['family'] ?? null, $perfume['description'] ?? null, $perfume['notes'], $perfume['tags'], $perfume['is_active'],
                    ));

                    foreach ($perfume['variants'] as $variant) {
                        $addVariant->execute(new AddPerfumeVariantCommand($stored->id->value, $variant['ml'], $variant['price']));
                    }

                    if ($photoPath !== null) {
                        PerfumeModel::query()->whereKey($stored->id->value)->update(['image_path' => $photoPath]);
                        $photos++;
                    }

                    $created++;
                }
            });
        }

        $this->command?->info(__('install.catalog_loaded', ['created' => $created, 'photos' => $photos]));

        if ($origin === null) {
            $this->command?->warn(__('install.catalog_without_photos'));
        }
    }

    /** The HTTPS origin of R2_PUBLIC_URL, or null when it is not configured. */
    private function photoOrigin(): ?string
    {
        $origin = rtrim((string) config('filesystems.disks.r2.url'), '/');

        return preg_match('#\Ahttps://[a-z0-9.-]+\z#i', $origin) === 1 ? $origin : null;
    }

    private function photoPath(?string $origin, ?string $key): ?string
    {
        if ($origin === null || $key === null || $key === '') {
            return null;
        }

        $path = "r2:{$origin}/".ltrim($key, '/');

        return strlen($path) <= self::MAX_PHOTO_PATH_LENGTH ? $path : null;
    }
}
